<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

use App\Domain\Order\ReadModel\Dtos\OrderStateDto;

final readonly class OrderDetailsDto {
    /**
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, string>> $payments
     * @param list<array{date: string, comment: string, user: string, snapshot: array<string, mixed>|null, changes: list<string>}> $statusHistory
     */
    public function __construct(
        public int $id,
        public string $createdAt,
        public string $desiredDate,
        public string $customerName,
        public string $customerPhone,
        public string $shippingAddress,
        public string $comments,
        public string $loyalty,
        public string $loyaltyComment,
        public string $prepayment,
        public string $shipmentPayment,
        public string $shippingCost,
        public string $shippedAmount,
        public string $paidAmount,
        public string $goodsTotal,
        public string $remainingAmount,
        public OrderStateDto $state,
        public array $lines,
        public array $payments,
        public array $statusHistory,
        public int $markup,
        public string $vatAmount,
        public string $goodsTotalWithMarkup,
        public string $totalWeight,
        public int $version = 0,
        public array $statusOptions = [],
    ) {
    }
}
