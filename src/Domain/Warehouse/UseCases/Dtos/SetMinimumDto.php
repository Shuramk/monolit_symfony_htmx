<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetMinimumDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Regex('/^(?:\\d{1,9}(?:\\.\\d{1,3})?)?$/')] public string $quantity = '',
        #[Assert\PositiveOrZero] public int $version = 0,
    ) {}
}
