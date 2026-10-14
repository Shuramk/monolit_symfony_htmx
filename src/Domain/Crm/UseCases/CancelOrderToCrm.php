<?php declare(strict_types=1);

namespace App\Domain\Crm\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Order\ReadModel\LegacyProductText;
use App\Domain\Order\ReadModel\OrderFinancialCalculator;
use App\Domain\Order\Repository\OrderViewRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class CancelOrderToCrm {
    public function __construct(
        private CrmRepository $repository,
        private OrderViewRepository $orders,
        private OrderFinancialCalculator $calculator,
        private LegacyProductText $text,
    ) {}

    /** Must run inside the transaction holding the order lock. */
    public function execute(array $source, int $actor, string $reason, \DateTimeImmutable $now): int {
        $groups = [];
        $financialLines = [];
        foreach ($source['lines'] as $line) {
            if ($line['product_id'] === null) {
                throw new \DomainException('Восстановите связь позиции с товаром перед отменой заказа.');
            }
            $productId = (int) $line['product_id'];
            $quantity = BigDecimal::of($line['quantity']);
            $amount = $quantity->multipliedBy($line['unit_price']);
            $previous = $groups[$productId] ?? ['quantity' => BigDecimal::zero(), 'amount' => BigDecimal::zero()];
            $groups[$productId] = [
                'quantity' => $previous['quantity']->plus($quantity),
                'amount' => $previous['amount']->plus($amount),
            ];
            $financialLines[] = [
                'Quantity' => $line['quantity'],
                'newPrice' => $line['unit_price'],
                'currentPrice' => $line['unit_price'],
            ];
        }
        $lines = [];
        foreach ($groups as $productId => $group) {
            if ($group['quantity']->isLessThanOrEqualTo(0)) {
                throw new \DomainException('Количество товара должно быть положительным.');
            }
            $lines[] = [
                'product_id' => $productId,
                'quantity' => (string) $group['quantity']->toScale(3),
                'price' => (string) $group['amount']->dividedBy($group['quantity'], 4, RoundingMode::HalfUp),
            ];
        }
        $financial = $this->calculator->calculate($financialLines, '0', (int) $source['markup'], '0', '0');
        $stamp = $now->format('Y-m-d H:i:s');
        $id = $this->repository->createDeal([
            'source_order_id' => $source['id'],
            'customer_id' => $source['customer_id'],
            'author_id' => $source['manager_id'],
            'created_at' => $source['created_at'],
            'desired_at' => substr($source['desired_at'], 0, 10),
            'shipping_address' => $source['shipping_address'],
            'comment' => (string) ($source['comments'] ?? ''),
            'director_comment' => '',
            'budget' => Decimal::fromScaled($financial->total, 2)->format(),
            'markup' => $source['markup'],
            'state' => 'cancelled',
        ]);
        $this->repository->replaceItems($id, $lines);
        $this->repository->contact($id, $actor, null, $stamp, $reason, $stamp);
        foreach (array_reverse($this->orders->statusHistory((int) $source['id'])) as $entry) {
            $details = [(string) $entry['changelog']];
            if ($entry['snapshot'] !== null) {
                $snapshot = json_decode($entry['snapshot'], true, flags: JSON_THROW_ON_ERROR);
                $details = array_merge($details, $this->snapshotDetails($snapshot));
            }
            $this->repository->history(
                'deal', $id, $entry['user_id'] === null ? null : (int) $entry['user_id'],
                $details, $entry['status_change_time'],
            );
        }
        $details = ['Заказ №' . $source['id'] . ' отменён. Причина: ' . $reason];
        foreach ($source['lines'] as $line) {
            $name = trim(($line['product_group_name'] ?? '') . ' ' . $line['product_name']);
            $details[] = $this->text->format($name) . ': ' . $line['quantity'] . ' '
                . $this->text->format($line['unit']) . ' по ' . $line['unit_price'] . ' грн';
        }
        $details[] = 'Заказчик: ' . $source['customer_name'] . ', ' . ($source['customer_phone'] ?? '');
        $this->repository->history(
            'deal', $id, $actor, $details,
            $stamp, ['cancel_comment' => $reason],
        );

        return $id;
    }

    private function snapshotDetails(array $snapshot): array {
        $details = [];
        foreach ($snapshot['lines'] ?? [] as $line) {
            $name = trim(($line['groupName'] ?? '') . ' ' . $line['name']);
            $details[] = $this->text->format($name) . ': ' . $line['quantity'] . ' '
                . $this->text->format($line['unit']) . ' по ' . $line['unitPrice'] . ' грн';
        }
        $details[] = 'Заказчик: ' . ($snapshot['customer']['name'] ?? '')
            . ', ' . ($snapshot['customer']['phone'] ?? '');
        $details[] = 'Адрес доставки: ' . ($snapshot['delivery']['address'] ?? '');
        $details[] = 'Дата доставки: ' . ($snapshot['delivery']['date'] ?? '');
        $details[] = 'Стоимость доставки: ' . ($snapshot['delivery']['cost'] ?? '0') . ' грн';
        $details[] = 'БН/ФОП: ' . ($snapshot['markup'] ?? 0) . '%';
        $details[] = 'Итого: ' . ($snapshot['total'] ?? '0') . ' грн';
        $details[] = 'Комментарий: ' . ($snapshot['comments'] ?? '');

        return $details;
    }
}
