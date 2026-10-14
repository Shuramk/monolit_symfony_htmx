<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use App\Domain\Product\Entities\Product;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateOrderDto implements RequestDtoInterface {
    /** @var array<int, Product> Loaded by the use case, never trusted from the request. */
    public array $products = [];

    /** @param list<UpdateOrderLineDto> $lines */
    public function __construct(
        #[Assert\PositiveOrZero]
        public readonly int $version,
        public readonly \DateTimeImmutable $desiredAt,
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public readonly array $lines,
        #[Assert\Range(min: 0, max: 20)]
        public readonly int $markup = 0,
        public readonly ?string $shippingAddress = null,
        public readonly ?string $comments = null,
        #[Assert\Regex(pattern: '/^\d+(?:\.\d{1,2})?$/')]
        public readonly string $shippingCost = '0',
    ) {}
}
