<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShippingApprovalDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Choice(choices: [0, 1])]
        public int $allowed,
        #[Assert\Choice(choices: [0, 1])]
        public int $expectedAllowed,
        public bool $returnToShipping = false,
    ) {}
}
