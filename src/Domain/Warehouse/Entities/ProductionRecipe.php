<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Entities;

use App\Domain\Common\ValueObjects\Decimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

final readonly class ProductionRecipe {
    public function __construct(private string $output, private array $components) {}

    /** Quantities in the materials' own units, not kilograms. */
    public function consumption(Decimal $produced, array $materials): array {
        $output = Decimal::fromString($this->output, 6);
        if ($output->isZero() || $output->isNegative()) throw new \DomainException('В рецепте не задан положительный выход продукции.');
        $result = [];
        foreach ($this->components as $id => $value) {
            $material = $materials[$id] ?? throw new \DomainException('Сырьё из рецепта не найдено.');
            if (!(bool)$material['production']) continue;
            $ratio = Decimal::fromString((string)$material['ratio'], 6);
            $component = BigRational::of((string)$value);
            if ($ratio->isZero() || $ratio->isNegative() || $component->isNegative()) throw new \DomainException('Некорректная единица измерения или количество сырья в рецепте.');
            $raw = $component->multipliedBy($produced->format())->dividedBy($output->format())->dividedBy($ratio->format())->toScale(3, RoundingMode::HalfUp);
            if ($raw->isGreaterThan('999999999999.999')) throw new \DomainException('Расход сырья превышает допустимый диапазон. Проверьте рецепт.');
            $result[(int)$id] = Decimal::fromString((string)$raw, 3);
        }
        return $result;
    }
}
