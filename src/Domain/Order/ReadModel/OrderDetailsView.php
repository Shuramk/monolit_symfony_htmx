<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Crm\ReadModel\CrmHistoryText;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ReadModel\Dtos\OrderDetailsDto;
use App\Domain\Order\Repository\OrderViewRepository;
use App\Domain\Warehouse\Repository\WarehouseViewRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class OrderDetailsView {
    public function __construct(
        private OrderViewRepository $orderViewRepository,
        private OrderStateCalculator $orderStateCalculator,
        private OrderFinancialCalculator $orderFinancialCalculator,
        private LegacyProductText $legacyProductText,
        private OrderChangelogChanges $changelogChanges,
        private WarehouseViewRepository $warehouse,
        private CrmHistoryText $crmHistoryText,
    ) {}

    public function find(int $id, bool $main = false): ?OrderDetailsDto {
        $source = $this->orderViewRepository->findCard($id);
        if ($source === null) {
            return null;
        }

        $shipped = [];
        foreach ($source->shipmentQuantities as $productId => $quantity) {
            $shipped[(int) $productId] = OrderDecimal::toScaled((string) $quantity, 3);
        }

        $markup = (int) $source->order['markup'];
        $financial = $this->orderFinancialCalculator->calculate(
            $source->lines,
            (string) $source->order["shippingCost"],
            $markup,
            (string) $source->order["prepayment"],
            $source->shipmentPayment,
        );
        $fullyShipped = $source->lines !== [];
        $lines = [];
        $shippedAmount = 0;
        $stock = $this->warehouse->stockForProducts(array_map(static fn(array $line): int => (int)$line['productID'], $source->lines));
        $totalWeight = BigDecimal::zero();
        foreach ($source->lines as $index => $line) {
            $productId = (int) $line["productID"];
            $orderedQuantity = OrderDecimal::toScaled((string) $line["Quantity"], 3);
            $shippedQuantity = $shipped[$productId] ?? 0;
            if (OrderDecimal::round($orderedQuantity, 3, 2) !== OrderDecimal::round($shippedQuantity, 3, 2)) {
                $fullyShipped = false;
            }
            $shippedAmount += OrderDecimal::round($shippedQuantity * $financial->lines[$index]["unitPrice"], 7, 2);
            $basePrice = Decimal::fromString((string) $line['newPrice'], 4);
            if ($basePrice->isNegative()) {
                $basePrice = Decimal::fromString((string) $line['currentPrice'], 4);
            }
            $priceWithMarkup = $markup === 20
                ? $basePrice->multiplyByInt(120)->divideByInt(100)->format(3)
                : Decimal::fromScaled($financial->lines[$index]['unitPrice'], 4)->format();
            $weight = BigDecimal::of((string)$line['weight_per_unit'])->multipliedBy((string)$line['Quantity'])->multipliedBy('0.001');
            $totalWeight = $totalWeight->plus($weight);
            $availability = $stock[$productId] ?? null;
            $reserved = Decimal::zero(3);
            $stockOrders = [];
            foreach ($availability['orders'] ?? [] as $stockOrder) {
                $quantity = Decimal::fromString((string)$stockOrder['quantity'], 3);
                $reserved = $reserved->add($quantity);
                $stockOrders[] = [
                    'order_id'=>(int)$stockOrder['order_id'], 'delivery_date'=>$stockOrder['delivery_date'], 'quantity'=>$quantity->format(2),
                ];
            }

            $lines[] = [
                "name" => $this->legacyProductText->format(trim((string) ($line["group_name"] ?? "")." ".(string) $line["name"])),
                "measure" => $this->legacyProductText->format((string) $line["measure"]),
                "quantity" => OrderDecimal::format($orderedQuantity, 3),
                "shippedQuantity" => OrderDecimal::format($shippedQuantity, 3),
                "unitPrice" => $basePrice->format(),
                "unitPriceWithMarkup" => $priceWithMarkup,
                "total" => OrderDecimal::format($financial->lines[$index]["total"]),
                'weight' => (string)$weight->toScale(3, RoundingMode::HalfUp),
                'stock' => $availability === null ? null : Decimal::fromString((string)$availability['storage_workshop'], 3)->format(2),
                'freeStock' => $availability === null ? null : Decimal::fromString((string)$availability['storage_workshop'], 3)->subtract(Decimal::fromString((string)$availability['task_quantity'], 3))->format(2),
                'reserved' => $reserved->format(2),
                'stockOrders' => $stockOrders,
            ];
        }

        $goodsTotal = Decimal::fromScaled($financial->goodsTotal, 2);
        $vatAmount = $markup === 20 ? $goodsTotal->multiplyByInt(20)->divideByInt(100) : Decimal::zero(2);
        $shippingCost = Decimal::fromString((string) $source->order['shippingCost'], 2);
        if ($markup > 0) {
            $shippingCost = $shippingCost->multiplyByInt(100 + $markup)->divideByInt(100);
        }
        if ($markup === 20) {
            $shippedAmount = Decimal::fromScaled($shippedAmount, 2)->multiplyByInt(120)->divideByInt(100)->scaledValue();
        }

        $prepayment = OrderDecimal::toScaled((string) $source->order["prepayment"], 2);
        $storedStatus = OrderStatus::tryFrom((string) $source->order['status']) ?? OrderStatus::New;
        $hasPayments = $prepayment > 0;
        foreach ($source->payments as $payment) {
            $hasPayments = $hasPayments || Decimal::fromString((string) $payment['sumOfPayment'], 2)->scaledValue() > 0;
        }
        $statusOptions = array_values(array_filter(
            OrderStatus::cases(),
            static fn (OrderStatus $status): bool => $status !== $storedStatus
                && ($status !== OrderStatus::PaidAndDelivered || $main)
                && ($status !== OrderStatus::Cancelled || (!$hasPayments && $source->shipmentQuantities === [])),
        ));
        $state = $this->orderStateCalculator->calculate(
            $financial->fullyPaid,
            $fullyShipped,
            OrderStatus::tryFrom((string) $source->order["status"]) ?? OrderStatus::New,
            $prepayment !== 0,
        );

        return new OrderDetailsDto(
            (int) $source->order["orderid"],
            (string) $source->order["order_time"],
            (string) $source->order["desired_date"],
            (string) $source->order["customerName"],
            (string) ($source->order["customerPhone"] ?? ""),
            (string) ($source->order["shipping_address"] ?? ""),
            (string) ($source->order["comments"] ?? ""),
            (string) ($source->order["loyalty"] ?? ""),
            (string) ($source->order["loyalty_comment"] ?? ""),
            OrderDecimal::format($prepayment),
            OrderDecimal::format(OrderDecimal::toScaled($source->shipmentPayment, 2)),
            $shippingCost->format(),
            OrderDecimal::format($shippedAmount),
            OrderDecimal::format($financial->paidAmount),
            OrderDecimal::format($financial->goodsTotal),
            OrderDecimal::format(max(0, $financial->total - $financial->paidAmount)),
            $state,
            $lines,
            $this->payments($source->payments),
            $this->statusHistory($source->statusHistory),
            markup: $markup,
            vatAmount: $vatAmount->format(),
            goodsTotalWithMarkup: $goodsTotal->add($vatAmount)->format(),
            totalWeight: (string)$totalWeight->toScale(3, RoundingMode::HalfUp),
            version: (int) $source->order['edit_version'],
            statusOptions: $statusOptions,
        );
    }

    /** @param list<array<string, mixed>> $payments @return list<array<string, string>> */
    private function payments(array $payments): array {
        return array_map(
            fn (array $payment): array => [
                "date" => (string) $payment["paymentDate"],
                "type" => (string) $payment["paymentType"],
                "amount" => OrderDecimal::format(OrderDecimal::toScaled((string) $payment["sumOfPayment"], 2)),
                "comment" => (string) ($payment["paymentComment"] ?? ""),
            ],
            $payments,
        );
    }

    /** @param list<array<string, mixed>> $history @return list<array<string, mixed>> */
    private function statusHistory(array $history): array {
        $comments = $this->crmHistoryText->format(array_column($history,'changelog'));
        foreach ($history as $index=>&$entry) {
            $entry['changelog'] = $comments[$index];
        }
        unset($entry);

        return array_map(
            function (array $entry): array {
                $snapshot = $entry['snapshot'] === null ? null : json_decode($entry['snapshot'], true, flags: JSON_THROW_ON_ERROR);

                return [
                    "date" => (string) $entry["status_change_time"],
                    "comment" => (string) $entry["changelog"],
                    "user" => (string) $entry["user_name"],
                    "snapshot" => $snapshot,
                    "changes" => isset($snapshot['context']['before']) ? $this->changelogChanges->compare($snapshot['context']['before'], $snapshot) : [],
                ];
            },
            $history,
        );
    }

}
