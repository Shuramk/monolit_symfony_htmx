<?php declare(strict_types=1);

namespace App\Tests\Domain\Common\ValueObjects;

use App\Domain\Common\ValueObjects\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase {
    public function testCalculatesWithExactScale(): void {
        $total = Decimal::fromString("330.0000", 4)
            ->multiply(Decimal::fromString("2.500", 3))
            ->round(2);

        self::assertSame("825.00", $total->format());
        self::assertSame(82500, $total->scaledValue());
    }

    public function testAddsDifferentScalesAndRoundsHalfAwayFromZero(): void {
        $sum = Decimal::fromString("1.20", 2)->add(Decimal::fromString("0.003", 3));

        self::assertSame("1.203", $sum->format());
        self::assertSame("1.24", Decimal::fromString("1.235", 3)->round(2)->format());
        self::assertSame("-1.24", Decimal::fromString("-1.235", 3)->round(2)->format());
    }

    public function testDividesAndRejectsInvalidInput(): void {
        self::assertSame("11.0000", Decimal::fromString("121.0000", 4)->divideByInt(11)->format());

        $this->expectException(\InvalidArgumentException::class);
        Decimal::fromString("12abc", 2);
    }
}
