<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use App\Domain\Order\Enums\OrderStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeOrderStatusDto implements RequestDtoInterface {
    public function __construct(
        public OrderStatus $status,
        #[Assert\PositiveOrZero]
        public int $version,
        #[Assert\Length(max: 20000)]
        public string $comment = '',
    ) {}
}
