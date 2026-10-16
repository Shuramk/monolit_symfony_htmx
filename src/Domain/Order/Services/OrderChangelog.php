<?php declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\ReadModel\OrderFinancialCalculator;
use App\Domain\Order\Repository\OrderChangelogRepository;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;

final readonly class OrderChangelog {
    public function __construct(
        private OrderChangelogRepository $repository,
        private OrderFinancialCalculator $calculator,
    ) {}

    public function created(int $orderId, CreateOrderDto $order, int $userId, \DateTimeImmutable $now): void {
        $discounts = [];
        foreach ($order->lines as $position => $line) {
            $discounts[] = [
                'position' => $position + 1,
                'productId' => $line->productId,
                'requestedUnitPrice' => $line->unitPrice,
                'discount' => $line->discount,
            ];
        }
        $this->record($orderId, 'created', 'Заказ оформлен', $userId, $now, ['lineInputs' => $discounts]);
    }

    /** Call inside the same transaction as the order change. @param array<string, mixed> $context */
    public function record(int $orderId, string $event, string $message, int $userId, \DateTimeImmutable $now, array $context = []): void {
        // Only creation and order edits need a complete order snapshot.
        $snapshot = in_array($event, ['created', 'updated'], true) ? $this->snapshot($orderId) : [];
        $snapshot['context'] = $context;
        $this->repository->append($orderId, $event, $message, $snapshot, $userId, $now);
    }

    /** @return array<string, mixed> */
    public function snapshot(int $orderId): array {
        $source = $this->repository->snapshot($orderId);
        $lines = [];
        $financialLines = [];
        foreach ($source['lines'] as $line) {
            $lines[] = [
                'lineId' => (int) $line['id'],
                'productId' => $line['product_id'] === null ? null : (int) $line['product_id'],
                'name' => (string) $line['product_name'],
                'groupName' => (string) ($line['product_group_name'] ?? ''),
                'unit' => (string) $line['unit'],
                'quantity' => (string) $line['quantity'],
                'unitPrice' => (string) $line['unit_price'],
                'position' => (int) $line['position'],
            ];
            $financialLines[] = ['Quantity' => $line['quantity'], 'newPrice' => $line['unit_price']];
        }
        $financial = $this->calculator->calculate($financialLines, (string) $source['shipping_cost'], (int) $source['markup'], '0', '0');
        foreach ($lines as $index => &$line) {
            $line['effectiveUnitPrice'] = Decimal::fromScaled($financial->lines[$index]['unitPrice'], 4)->format();
            $line['total'] = Decimal::fromScaled($financial->lines[$index]['total'], 2)->format();
        }
        unset($line);

        return [
            'version' => 1,
            'orderId' => (int) $source['id'],
            'status' => (string) $source['status'],
            'customer' => ['id' => $source['customer_id'] === null ? null : (int) $source['customer_id'], 'name' => (string) $source['customer_name'], 'phone' => $source['customer_phone']],
            'lines' => $lines,
            'delivery' => [
                'address' => $source['shipping_address'], 'date' => (string) $source['desired_at'],
                'cost' => (string) $source['shipping_cost'], 'carrierId' => $source['carrier_id'] ?? null,
                'trips' => $source['shipping_trips'] ?? null, 'unitPrice' => $source['shipping_unit_price'] ?? null,
            ],
            'markup' => (int) $source['markup'],
            'goodsTotal' => Decimal::fromScaled($financial->goodsTotal, 2)->format(),
            'total' => Decimal::fromScaled($financial->total, 2)->format(),
            'comments' => $source['comments'],
            'context' => [],
        ];
    }
}
