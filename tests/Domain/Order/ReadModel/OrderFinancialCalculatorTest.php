<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\OrderFinancialCalculator;
use PHPUnit\Framework\TestCase;

final class OrderFinancialCalculatorTest extends TestCase
{
    public function testItCalculatesMarkupShippingAndPaymentWithFixedPointDecimals(): void
    {
        $financial = (new OrderFinancialCalculator())->calculate(
            [["Quantity" => "2.500", "newPrice" => "10.0000", "currentPrice" => "9.00"]],
            "3.00",
            10,
            "20.00",
            "10.80",
        );

        self::assertSame(3080, $financial->total);
        self::assertSame(3080, $financial->paidAmount);
        self::assertTrue($financial->fullyPaid);
        self::assertSame(110000, $financial->lines[0]["unitPrice"]);
        self::assertSame(2750, $financial->lines[0]["total"]);
    }

    public function testItUsesCurrentPriceForNegativeLegacyPriceAndAppliesTwentyPercentAtTheEnd(): void
    {
        $financial = (new OrderFinancialCalculator())->calculate(
            [["Quantity" => "2.000", "newPrice" => "-1.0000", "currentPrice" => "4.00"]],
            "2.00",
            20,
            "12.00",
            "0.00",
        );

        self::assertSame(1200, $financial->total);
        self::assertTrue($financial->fullyPaid);
        self::assertSame(40000, $financial->lines[0]["unitPrice"]);
    }
}
