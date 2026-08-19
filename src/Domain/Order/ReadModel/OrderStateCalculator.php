<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ReadModel\Dtos\OrderStateDto;

final class OrderStateCalculator
{
    public function calculate(bool $fullyPaid, bool $fullyShipped, OrderStatus $currentStatus, bool $hasPrepayment): OrderStateDto
    {
        $canBeMarkedPaidAndDelivered = $fullyPaid
            && ($fullyShipped || $currentStatus === OrderStatus::Delivered)
            && $hasPrepayment
            && $currentStatus !== OrderStatus::PaidAndDelivered
            && $currentStatus !== OrderStatus::Cancelled;

        return new OrderStateDto(
            $fullyPaid,
            $fullyShipped,
            $canBeMarkedPaidAndDelivered ? OrderStatus::PaidAndDelivered : $currentStatus,
        );
    }
}
