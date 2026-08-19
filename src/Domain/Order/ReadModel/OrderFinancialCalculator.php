<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\ReadModel\Dtos\OrderFinancialDto;

final class OrderFinancialCalculator {
    /** @param list<array<string, mixed>> $lines */
    public function calculate(array $lines, string $shippingCost, int $markup, string $prepayment, string $shipmentPayment): OrderFinancialDto {
        $totalAtFourDecimalPlaces = Decimal::zero(4);
        $calculatedLines = [];
        foreach ($lines as $line) {
            $orderedQuantity = Decimal::fromString((string) $line["Quantity"], 3);
            $unitPrice = Decimal::fromString((string) $line["newPrice"], 4);
            if ($unitPrice->isNegative()) {
                $unitPrice = Decimal::fromString((string) $line["currentPrice"], 4);
            }
            if ($markup > 0 && $markup < 20) {
                $unitPrice = $unitPrice->multiplyByInt(100 + $markup)->divideByInt(100);
            }

            $lineTotal = $orderedQuantity->multiply($unitPrice)->round(2);
            $totalAtFourDecimalPlaces = $totalAtFourDecimalPlaces->add($lineTotal->toScale(4));
            $calculatedLines[] = ["unitPrice" => $unitPrice->scaledValue(), "total" => $lineTotal->scaledValue()];
        }

        $goodsTotal = $totalAtFourDecimalPlaces->round(2);
        $shipping = Decimal::fromString($shippingCost, 2)->multiplyByInt($markup > 0 && $markup < 20 ? 100 + $markup : 100)->divideByInt(100)->toScale(4);
        $total = $totalAtFourDecimalPlaces->add($shipping)->round(2);
        if ($markup === 20) {
            $total = $total->add($total->multiplyByInt(20)->divideByInt(100));
        }

        $prepaymentAmount = Decimal::fromString($prepayment, 2);
        $paidAmount = $prepaymentAmount->add(Decimal::fromString($shipmentPayment, 2));

        return new OrderFinancialDto(
            $goodsTotal->scaledValue(),
            $total->scaledValue(),
            $paidAmount->scaledValue(),
            !$prepaymentAmount->isZero() && $paidAmount->equals($total),
            $calculatedLines,
        );
    }
}
