<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShippingActionDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $version,
    ) {}
}
