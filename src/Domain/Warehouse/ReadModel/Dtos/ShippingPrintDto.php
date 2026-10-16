<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class ShippingPrintDto {
    public function __construct(
        public array $task,
        public array $order,
        public array $lines,
        public string $kind,
        public string $number,
        public string $date,
        public string $subtotal,
        public ?string $vat,
        public string $total,
    ) {}
}
