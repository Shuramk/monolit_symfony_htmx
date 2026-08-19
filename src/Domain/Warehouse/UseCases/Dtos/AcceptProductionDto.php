<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AcceptProductionDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Uuid] public string $operationId,
        #[Assert\Regex('/^\\d{1,8}(?:\\.\\d{1,3})?$/')] public ?string $quantity = null,
        #[Assert\Choice(choices: [1,2])] public int $storage = 2,
        #[Assert\Length(max:255)] public string $comment = '',
    ) {}
}
