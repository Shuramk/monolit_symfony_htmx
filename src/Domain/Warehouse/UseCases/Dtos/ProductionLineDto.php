<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProductionLineDto {
    public function __construct(
        #[Assert\Positive] public int $productId,
        #[Assert\Regex('/^\\d{1,8}(?:\\.\\d{1,3})?$/')] public string $quantity,
    ) {}
}
