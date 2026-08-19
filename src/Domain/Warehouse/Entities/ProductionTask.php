<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Entities;

use App\Domain\Common\ValueObjects\Decimal;

final class ProductionTask {
    public function __construct(public readonly int $id, public readonly int $productId, public readonly int $pressId, private Decimal $quantity, private bool $done) {}

    public function completePart(Decimal $quantity, Decimal $completed): bool {
        if ($this->done) throw new \DomainException('Задача уже завершена.');
        if ($quantity->isNegative() || $quantity->isZero()) throw new \DomainException('Укажите положительное количество.');
        $this->done = $completed->add($quantity)->scaledValue() >= $this->quantity->toScale(3)->scaledValue();
        return $this->done;
    }
}
