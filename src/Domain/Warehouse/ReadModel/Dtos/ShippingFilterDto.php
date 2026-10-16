<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShippingFilterDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Length(max: 1000)]
        public string $q = '',
        #[Assert\Positive]
        public int $page = 1,
    ) {}

    public function parameters(): array {
        $parameters = [];
        if ($this->q !== '') {
            $parameters['q'] = $this->q;
        }
        if ($this->page > 1) {
            $parameters['page'] = $this->page;
        }

        return $parameters;
    }
}
