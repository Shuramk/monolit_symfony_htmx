<?php declare(strict_types=1);

namespace App\Domain\Product\UseCases\Dtos;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductSortingRowDto {
    public function __construct(
        #[Assert\Positive] public int $id,
        #[Assert\PositiveOrZero] public int $version,
        #[Assert\Range(min: 0, max: 2147483647)] public int $sorting,
    ) {}
}
