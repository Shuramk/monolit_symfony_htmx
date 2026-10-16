<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\ReadModel\LegacyProductText;
use App\Domain\Warehouse\ReadModel\Dtos\ShippingFilterDto;
use App\Domain\Warehouse\ReadModel\Dtos\ShippingFormDto;
use App\Domain\Warehouse\ReadModel\Dtos\ShippingPageDto;
use App\Domain\Warehouse\ReadModel\Dtos\ShippingPrintDto;
use App\Domain\Warehouse\ReadModel\Dtos\ShippingTaskStateDto;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Domain\Warehouse\Repository\ShippingRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ShippingView {
    public function __construct(private ShippingRepository $repository, private LegacyProductText $text) {}

    public function state(int $id): ShippingTaskStateDto {
        $task = $this->repository->task($id);

        return new ShippingTaskStateDto(
            (int) $task['id'],
            (int) $task['version'],
            (int) $task['status'],
            (int) ($task['payload']['collected'] ?? 0) === 1,
            (int) ($task['payload']['allowed'] ?? 0) === 1,
        );
    }

    public function page(ShippingFilterDto $filter, bool $archive, bool $workshopOnly = false): ShippingPageDto {
        $page = $this->repository->page($archive, $filter->q, $filter->page, $workshopOnly);
        $lines = [];
        foreach ($this->repository->lines(array_column($page['rows'], 'id')) as $line) {
            $line['name'] = $this->text->format($line['name'] ?? 'Продукт №' . $line['product_id']);
            $line['unit'] = $this->text->format($line['unit'] ?? '');
            $lines[(int) $line['task_id']][] = $line;
        }
        $carriers = array_column($this->repository->carriers(), 'name', 'id');
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('tomorrow', new \DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
        foreach ($page['rows'] as &$row) {
            $payload = json_decode($row['payload'] ?? '{}', true, flags: JSON_THROW_ON_ERROR) ?? [];
            $row['lines'] = $lines[(int) $row['id']] ?? [];
            $row['storage'] = (int) ($payload['storage'] ?? 2);
            $row['carrier'] = empty($payload['carrierID']) ? 'Самовывоз' : ($carriers[$payload['carrierID']] ?? 'Перевозчик №' . $payload['carrierID']);
            $row['shippingPrice'] = $payload['shipmentPrice'] ?? '0.00';
            $row['collected'] = (int) ($payload['collected'] ?? 0) === 1;
            $row['allowed'] = (int) ($payload['allowed'] ?? 0) === 1;
            $date = substr((string) $row['performance_date'], 0, 10);
            $row['dueClass'] = $archive || $date === '' ? '' : match (true) {
                $date < $today => 'shipping-due-overdue',
                $date === $today => 'shipping-due-today',
                $date === $tomorrow => 'shipping-due-tomorrow',
                default => 'shipping-due-future',
            };
        }
        unset($row);

        return new ShippingPageDto($page['rows'], $archive, $filter->q, $page['page'], $page['pages'], $page['total']);
    }

    public function form(?int $orderId = null, ?int $taskId = null): ShippingFormDto {
        $task = $taskId === null ? null : $this->repository->task($taskId);
        if ($task !== null && ((int) $task['status'] !== 0 || $task['order_id'] === null)) {
            throw new ConflictHttpException('Задача выполнена, удалена или относится к перемещению.');
        }
        $orderId = $task === null ? $orderId : (int) $task['order_id'];
        $order = $this->repository->orderSource($orderId ?? 0);
        if ($order['status'] === 'cancelled') {
            throw new \DomainException('Заказ отменён.');
        }
        $planned = $this->repository->planned((int) $order['id'], $taskId ?? 0);
        $reservations = [];
        foreach ($this->repository->reservationsForProducts(array_column($order['lines'], 'product_id')) as $reservation) {
            $reservations[(int) $reservation['product_id']][] = $reservation;
        }
        $current = [];
        foreach ($task['lines'] ?? [] as $line) {
            $id = (int) $line['product_id'];
            $current[$id] = ($current[$id] ?? Decimal::zero(3))->add(Decimal::fromString($line['quantity'], 3));
        }
        $lines = [];
        $totalWeight = BigDecimal::zero();
        foreach ($order['lines'] as $line) {
            $id = (int) $line['product_id'];
            $shipped = (string) ($order['shipped'][$id] ?? '0');
            $reserved = (string) ($planned[$id] ?? '0');
            $remaining = Decimal::fromString($line['quantity'], 3)
                ->subtract(Decimal::fromString($shipped, 3))
                ->subtract(Decimal::fromString($reserved, 3));
            $line['name'] = $this->text->format(trim(($line['group_name'] ?? '') . ' ' . $line['name']));
            $line['unit'] = $this->text->format($line['unit']);
            $line['shipped'] = $shipped;
            $line['planned'] = $reserved;
            $line['remaining'] = $remaining->isNegative() ? '0.000' : $remaining->format(3);
            $line['selected'] = $task === null ? $line['remaining'] : ($current[$id] ?? Decimal::zero(3))->format(3);
            $line['reservations'] = $reservations[$id] ?? [];
            $reservedTotal = Decimal::zero(3);
            $free = [
                1 => Decimal::fromString((string) ($line['office'] ?? '0'), 3),
                2 => Decimal::fromString((string) ($line['workshop'] ?? '0'), 3),
            ];
            $line['stockTotal'] = $free[1]->add($free[2])->format(3);
            foreach ($line['reservations'] as $reservation) {
                $reservedQuantity = Decimal::fromString($reservation['quantity'], 3);
                $reservedTotal = $reservedTotal->add($reservedQuantity);
                $storage = (int) $reservation['storage'];
                if ((int) $reservation['task_id'] !== $taskId && isset($free[$storage])) {
                    $free[$storage] = $free[$storage]->subtract($reservedQuantity);
                }
            }
            $line['reservedTotal'] = $reservedTotal->format(3);
            $line['freeOffice'] = $free[1]->format(3);
            $line['freeWorkshop'] = $free[2]->format(3);
            $line['weightPerUnit'] = (string) ($line['weight_per_unit'] ?? '0');
            $weight = BigDecimal::of($line['selected'])->multipliedBy($line['weightPerUnit'])->dividedBy('1000', 6, RoundingMode::HalfUp);
            $line['weight'] = (string) $weight->toScale(3, RoundingMode::HalfUp);
            $totalWeight = $totalWeight->plus($weight);
            $lines[] = $line;
        }
        $date = $task['performance_date'] ?? (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Kyiv')))->format('Y-m-d H:i:s');
        $payload = $task['payload'] ?? [];
        $carriers = $this->repository->carriers();
        $carrierId = $task === null ? (int) ($carriers[0]['id'] ?? 0) : (int) ($payload['carrierID'] ?? 0);

        return new ShippingFormDto(
            $order, $task, $lines, $carriers,
            (int) ($task['version'] ?? $order['edit_version']),
            substr($date, 0, 10), substr($date, 11, 5),
            (int) ($payload['storage'] ?? 2), $carrierId,
            (string) ($payload['shipmentPrice'] ?? '0.00'), (string) ($task['comment'] ?? ''),
            $this->operationId(),
            $carrierId === 0,
            (string) $totalWeight->toScale(3, RoundingMode::HalfUp),
        );
    }

    private function operationId(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    public function document(int $id, string $kind): ShippingPrintDto {
        $task = $this->repository->task($id);
        if ($task['order_id'] === null || (int) $task['status'] === 2) {
            throw new NotFoundHttpException('Накладная по задаче не найдена.');
        }
        $order = $this->repository->orderSource((int) $task['order_id']);
        $prices = array_column($order['lines'], 'price', 'product_id');
        $markup = (int) $order['markup'];
        $subtotal = Decimal::zero(2);
        $lines = [];
        foreach ($task['lines'] as $line) {
            $price = (int) $task['status'] === 1 ? $line['price'] : ($prices[$line['product_id']] ?? $line['price']);
            $price = BigDecimal::of($price ?? '0')->toScale(4, RoundingMode::HalfUp);
            $price = Decimal::fromString((string) $price, 4);
            if ($markup > 0 && $markup < 20) {
                $price = $price->multiplyByInt(100 + $markup)->divideByInt(100);
            }
            $quantity = Decimal::fromString($line['quantity'], 3);
            $total = $quantity->multiply($price)->round(2);
            $subtotal = $subtotal->add($total);
            $lines[] = [
                'name' => $this->text->format($line['name'] ?: 'Продукт №' . $line['product_id']),
                'unit' => $this->text->format($line['unit'] ?? ''),
                'quantity' => $quantity->format(3), 'price' => $price->format(2), 'total' => $total->format(2),
                'pallets' => $this->pallets($line['pallets'] ?? '', $line['quantity']),
            ];
        }
        $vat = $markup === 20 ? $subtotal->multiplyByInt(20)->divideByInt(100)->round(2) : null;

        return new ShippingPrintDto(
            $task, $order, $lines, $kind,
            $order['id'] . '-' . $this->repository->invoiceNumber((int) $order['id'], $id),
            (string) ($task['completed_at'] ?? $task['performance_date'] ?? date('Y-m-d')),
            $subtotal->format(2), $vat?->format(2), $subtotal->add($vat ?? Decimal::zero(2))->format(2),
        );
    }

    private function pallets(string $settings, string $quantity): array {
        $values = explode(';', str_replace(',', '.', $settings));
        $result = [];
        foreach ([0 => '0,8×1,2', 3 => '1,0×1,2'] as $offset => $name) {
            $capacity = trim($values[$offset] ?? '');
            $perRow = trim($values[$offset + 2] ?? '');
            if (!preg_match('/^\d+(?:\.\d+)?$/D', $capacity) || BigDecimal::of($capacity)->isLessThanOrEqualTo(0)) {
                continue;
            }
            $amount = BigDecimal::of($quantity);
            $count = $amount->dividedBy($capacity, 0, RoundingMode::Down);
            $rows = null;
            if (preg_match('/^\d+(?:\.\d+)?$/D', $perRow) && BigDecimal::of($perRow)->isGreaterThan(0)) {
                $rows = (string) $amount->minus($count->multipliedBy($capacity))->dividedBy($perRow, 1, RoundingMode::HalfUp);
            }
            $result[] = ['name' => $name, 'count' => (string) $count, 'rows' => $rows];
        }

        return $result;
    }
}
