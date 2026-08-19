<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;

/** @deprecated Use Decimal directly in new code. */
final class OrderDecimal {
    public static function toScaled(string $value, int $scale): int {
        return Decimal::fromString($value, $scale)->scaledValue();
    }

    public static function round(int $value, int $fromScale, int $toScale): int {
        return Decimal::fromScaled($value, $fromScale)->round($toScale)->scaledValue();
    }

    public static function divideRounded(int $value, int $divisor): int {
        return Decimal::fromScaled($value, 0)->divideByInt($divisor)->scaledValue();
    }

    public static function format(int $value, int $scale = 2): string {
        return Decimal::fromScaled($value, $scale)->format();
    }
}
