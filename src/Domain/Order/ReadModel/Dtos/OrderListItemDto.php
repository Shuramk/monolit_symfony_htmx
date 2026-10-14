<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

use App\Domain\Order\Enums\OrderStatus;

final readonly class OrderListItemDto
{
    public function __construct(
        public int $id,
        public string $createdAt,
        public string $desiredDate,
        public string $customerName,
        public string $shippingAddress,
        public OrderStatus $status,
        public string $statusTitle,
        public string $statusColor,
    ) {
    }
}
