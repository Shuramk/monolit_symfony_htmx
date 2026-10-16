<?php declare(strict_types=1);

namespace App\Domain\Customer\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCustomerDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $name,
        #[Assert\Regex(pattern: '/^[+()\d\s-]{6,32}$/u')]
        public string $phone,
        #[Assert\Regex(pattern: '/^[a-f0-9]{32}$/iD')]
        public string $operationId,
    ) {}
}
