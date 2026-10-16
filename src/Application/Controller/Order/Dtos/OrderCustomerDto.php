<?php declare(strict_types=1);

namespace App\Application\Controller\Order\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderCustomerDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Positive]
        public int $customerId,
        public \DateTimeImmutable $desiredAt,
        public ?string $shippingAddress = null,
        public ?string $comments = null,
        #[Assert\Regex(pattern: '/^\d+(?:\.\d{1,2})?$/')]
        public string $shippingCost = "0",
        public ?bool $pickup = null,
        #[Assert\PositiveOrZero]
        public int $carrierId = 0,
        #[Assert\Range(min: 0, max: 10000)]
        public int $shippingTrips = 1,
        #[Assert\Regex(pattern: '/^\d{1,8}(?:\.\d{1,2})?$/')]
        public string $shippingUnitPrice = '0',
    ) {
    }
}
