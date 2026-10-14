<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeStockDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Regex('/^\\d{1,8}(?:\\.\\d{1,3})?$/')] public string $quantity,
        #[Assert\Uuid] public string $operationId,
        #[Assert\Choice(choices: [1,2])] public int $storage = 2,
        #[Assert\Length(max:255)] public string $comment = '',
        #[Assert\Choice(choices: [111,211,212,221,222])] public ?int $shift = null,
        #[Assert\Date] public ?string $date = null,
    ) {}
}
