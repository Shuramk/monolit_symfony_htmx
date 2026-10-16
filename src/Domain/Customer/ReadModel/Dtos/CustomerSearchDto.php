<?php declare(strict_types=1);

namespace App\Domain\Customer\ReadModel\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CustomerSearchDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Length(max: 100)]
        public string $q = '',
    ) {}
}
