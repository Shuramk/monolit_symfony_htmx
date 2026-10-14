<?php declare(strict_types=1);
namespace App\Domain\Crm\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SaveTaskDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max:20000)] public string $text,
        #[Assert\Positive] public int $executorId,
        public string $dueAt,
        public array $coexecutors = [],
        public array $observers = [],
        #[Assert\Length(max:20000)] public string $directorComment = '',
        #[Assert\Length(max:20000)] public string $lastComment = '',
        public ?int $version = null,
    ) {}
}
