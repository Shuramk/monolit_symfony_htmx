<?php

declare(strict_types=1);

namespace App\Domain\Order\UseCases\Dtos;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderLineDto {
    public function __construct(
        #[Assert\Positive]
        public int $productId,
        #[Assert\Regex(pattern: '/^\d+(?:\.\d{1,3})?$/')]
        #[Assert\GreaterThan(0)]
        public string $quantity,
        #[Assert\Regex(pattern: '/^\d+(?:\.\d{1,4})?$/')]
        public ?string $unitPrice = null,
        #[Assert\Regex(pattern: '/^-?\d+(?:\.\d{1,2})?$/')]
        #[Assert\Range(min: -100, max: 100)]
        public string $discount = "0",
    ) {
    }
}
