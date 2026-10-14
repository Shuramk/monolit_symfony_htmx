<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductionDto implements RequestDtoInterface {
    /** @param ProductionLineDto[] $lines */
    public function __construct(
        #[Assert\Count(min:1,max:500), Assert\Valid] public array $lines,
        #[Assert\Uuid] public string $operationId,
        #[Assert\Date] public string $date,
        #[Assert\Choice(choices: [0,1,2])] public int $press = 1,
        #[Assert\Length(max:2000)] public string $comment = '',
    ) {}
}
