<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

final class LegacyProductText {
    public function format(string $value): string {
        return str_replace(
            ["<sup>2</sup>", "<sup>3</sup>", "<sub>2</sub>", "<sub>3</sub>"],
            ["²", "³", "₂", "₃"],
            $value,
        );
    }
}
