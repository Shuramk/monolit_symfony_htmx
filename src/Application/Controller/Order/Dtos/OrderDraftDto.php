<?php declare(strict_types=1);

namespace App\Application\Controller\Order\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderDraftDto implements RequestDtoInterface {
    /** @param list<CreateOrderLineDto> $lines */
    public function __construct(
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $lines,
        #[Assert\Range(min: 0, max: 20)]
        public int $markup = 0,
    ) {
    }
}
