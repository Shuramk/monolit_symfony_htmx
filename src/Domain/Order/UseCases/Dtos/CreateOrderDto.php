<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases\Dtos;

use App\Domain\Product\Entities\Product;

final class CreateOrderDto {
    /** @var array<int, Product> Products indexed by ID, loaded only by the use case. */
    public array $products = [];

    /** @param list<CreateOrderLineDto> $lines */
    public function __construct(
        public readonly int $customerId,
        public readonly \DateTimeImmutable $desiredAt,
        public readonly ?string $shippingAddress,
        public readonly ?string $comments,
        public readonly string $shippingCost,
        public readonly int $markup,
        public readonly array $lines,
    ) {
    }
}
