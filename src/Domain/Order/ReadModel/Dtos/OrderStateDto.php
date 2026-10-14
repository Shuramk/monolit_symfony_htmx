<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

use App\Domain\Order\Enums\OrderStatus;

final readonly class OrderStateDto
{
    public function __construct(
        public bool $fullyPaid,
        public bool $fullyShipped,
        public OrderStatus $displayStatus,
    ) {
    }
}
