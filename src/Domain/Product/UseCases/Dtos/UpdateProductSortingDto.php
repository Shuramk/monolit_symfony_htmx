<?php declare(strict_types=1);

namespace App\Domain\Product\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductSortingDto implements RequestDtoInterface {
    /** @param list<UpdateProductSortingRowDto> $rows */
    public function __construct(#[Assert\Count(min: 1, max: 1000)] #[Assert\Valid] public array $rows) {}
}
