<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShippingTaskDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $version,
        #[Assert\Date]
        public string $date,
        #[Assert\Count(min: 1, max: 500)]
        public array $lines,
        public string $operationId = '',
        #[Assert\Regex(pattern: '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/')]
        public string $time = '00:00',
        #[Assert\Choice(choices: [1, 2])]
        public int $storage = 2,
        #[Assert\PositiveOrZero]
        public int $carrierId = 0,
        #[Assert\Regex(pattern: '/^\d{1,10}(?:\.\d{1,2})?$/')]
        public string $shippingPrice = '0',
        #[Assert\Length(max: 20000)]
        public string $comment = '',
        public bool $pickup = false,
        public bool $allowed = false,
    ) {}
}
