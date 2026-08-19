<?php declare(strict_types=1);
namespace App\Domain\Crm\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SaveDealDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Positive] public int $customerId,
        #[Assert\Length(max:20000)] public string $comment = '',
        public string $budget = '0',
        #[Assert\Range(min:0,max:100)] public int $markup = 0,
        public array $lines = [],
        public ?int $executorId = null,
        public ?string $contactAt = null,
        #[Assert\Length(max:20000)] public string $contactComment = '',
        public ?string $desiredAt = null,
        public ?string $shippingAddress = null,
        public ?int $stackerId = null,
        #[Assert\Range(min:0,max:5)] public ?int $sourceId = null,
        #[Assert\Length(max:20000)] public string $directorComment = '',
        public ?int $version = null,
        public bool $toOrder = false,
    ) {}
}
