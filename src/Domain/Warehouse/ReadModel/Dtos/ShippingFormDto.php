<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class ShippingFormDto {
    public function __construct(
        public array $order,
        public ?array $task,
        public array $lines,
        public array $carriers,
        public int $version,
        public string $date,
        public string $time,
        public int $storage,
        public int $carrierId,
        public string $shippingPrice,
        public string $comment,
        public string $operationId,
        public bool $pickup,
        public string $totalWeight,
    ) {}
}
