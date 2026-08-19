<?php declare(strict_types=1);

namespace App\Domain\Common\ValueObjects;

final readonly class Decimal {
    private function __construct(
        private int $value,
        private int $scale,
    ) {
    }

    public static function fromString(string $value, int $scale): self {
        self::assertScale($scale);
        $value = trim($value);
        if (preg_match("/^(?<sign>-?)(?<whole>\d+)(?:\.(?<fraction>\d+))?$/", $value, $matches) !== 1) {
            throw new \InvalidArgumentException("Expected a decimal value.");
        }

        $fraction = $matches["fraction"] ?? "";
        $digits = $matches["whole"].str_pad($fraction, $scale, "0");
        $scaled = (int) substr($digits, 0, strlen($matches["whole"]) + $scale);
        if (($fraction[$scale] ?? "0") >= "5") {
            ++$scaled;
        }

        return new self($matches["sign"] === "-" ? -$scaled : $scaled, $scale);
    }

    public static function fromScaled(int $value, int $scale): self {
        self::assertScale($scale);

        return new self($value, $scale);
    }

    public static function zero(int $scale): self {
        return self::fromScaled(0, $scale);
    }

    public function scaledValue(): int {
        return $this->value;
    }

    public function scale(): int {
        return $this->scale;
    }

    public function isNegative(): bool {
        return $this->value < 0;
    }

    public function isZero(): bool {
        return $this->value === 0;
    }

    public function add(self $other): self {
        $scale = max($this->scale, $other->scale);

        return self::fromScaled($this->toScale($scale)->value + $other->toScale($scale)->value, $scale);
    }

    public function subtract(self $other): self {
        return $this->add(self::fromScaled(-$other->value, $other->scale));
    }

    public function multiply(self $other): self {
        return self::fromScaled($this->value * $other->value, $this->scale + $other->scale);
    }

    public function multiplyByInt(int $multiplier): self {
        return self::fromScaled($this->value * $multiplier, $this->scale);
    }

    public function divideByInt(int $divisor): self {
        if ($divisor <= 0) {
            throw new \InvalidArgumentException("The divisor must be positive.");
        }

        return self::fromScaled(self::divideRounded($this->value, $divisor), $this->scale);
    }

    public function toScale(int $scale): self {
        self::assertScale($scale);
        if ($scale === $this->scale) {
            return $this;
        }
        if ($scale > $this->scale) {
            return self::fromScaled($this->value * (10 ** ($scale - $this->scale)), $scale);
        }

        return self::fromScaled(self::roundScaled($this->value, $this->scale, $scale), $scale);
    }

    public function round(int $scale): self {
        return $this->toScale($scale);
    }

    public function equals(self $other): bool {
        $scale = max($this->scale, $other->scale);

        return $this->toScale($scale)->value === $other->toScale($scale)->value;
    }

    public function format(?int $scale = null): string {
        $decimal = $scale === null ? $this : $this->toScale($scale);
        $sign = $decimal->value < 0 ? "-" : "";
        $absolute = (string) abs($decimal->value);
        if ($decimal->scale === 0) {
            return $sign.$absolute;
        }

        $absolute = str_pad($absolute, $decimal->scale + 1, "0", STR_PAD_LEFT);

        return $sign.substr($absolute, 0, -$decimal->scale).".".substr($absolute, -$decimal->scale);
    }

    private static function roundScaled(int $value, int $fromScale, int $toScale): int {
        $divisor = 10 ** ($fromScale - $toScale);
        $absolute = abs($value);
        $rounded = intdiv($absolute, $divisor);
        if ($absolute % $divisor * 2 >= $divisor) {
            ++$rounded;
        }

        return $value < 0 ? -$rounded : $rounded;
    }

    private static function divideRounded(int $value, int $divisor): int {
        $absolute = abs($value);
        $result = intdiv($absolute, $divisor);
        if ($absolute % $divisor * 2 >= $divisor) {
            ++$result;
        }

        return $value < 0 ? -$result : $result;
    }

    private static function assertScale(int $scale): void {
        if ($scale < 0) {
            throw new \InvalidArgumentException("The scale must not be negative.");
        }
    }
}
