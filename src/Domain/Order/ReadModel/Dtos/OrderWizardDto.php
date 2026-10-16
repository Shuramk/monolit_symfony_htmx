<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

use App\Domain\Customer\ReadModel\Dtos\CustomerChoiceDto;

final readonly class OrderWizardDto {
    public function __construct(
        public array $lines,
        public int $markup,
        public string $goodsTotal,
        public string $total,
        public string $totalWeight,
        public ?CustomerChoiceDto $customer,
        public array $carriers,
        public string $operationId,
    ) {}
}
