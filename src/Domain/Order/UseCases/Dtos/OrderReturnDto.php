<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderReturnDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Uuid] public string $operationId,
        public array $items = [],
        #[Assert\PositiveOrZero] public int $orderVersion = 0,
        #[Assert\Positive] public ?int $draftId = null,
        #[Assert\Length(max: 20000)] public string $comment = '',
    ) {}
}
