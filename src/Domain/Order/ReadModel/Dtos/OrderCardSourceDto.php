<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

final readonly class OrderCardSourceDto
{
    /**
     * @param list<array<string, mixed>> $lines
     * @param array<int|string, int|float|string> $shipmentQuantities
     * @param list<array<string, mixed>> $payments
     * @param list<array<string, mixed>> $statusHistory
     */
    public function __construct(
        public array $order,
        public array $lines,
        public array $shipmentQuantities,
        public string $shipmentPayment,
        public array $payments,
        public array $statusHistory,
    ) {
    }
}
