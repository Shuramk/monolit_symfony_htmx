<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ReadModel\OrderStateCalculator;
use PHPUnit\Framework\TestCase;

final class OrderStateCalculatorTest extends TestCase {
    public function testItRecommendsPaidAndDeliveredForACompletePrepaidOrder(): void {
        $state = (new OrderStateCalculator())->calculate(true, true, OrderStatus::InProgress, true);

        self::assertTrue($state->fullyPaid);
        self::assertTrue($state->fullyShipped);
        self::assertSame(OrderStatus::PaidAndDelivered, $state->displayStatus);
    }

    public function testItKeepsStatusWhenPaymentOrShipmentIsIncomplete(): void {
        $calculator = new OrderStateCalculator();

        self::assertSame(OrderStatus::InProgress, $calculator->calculate(false, true, OrderStatus::InProgress, true)->displayStatus);
        self::assertSame(OrderStatus::InProgress, $calculator->calculate(true, false, OrderStatus::InProgress, true)->displayStatus);
    }

    public function testItDoesNotReplaceCancelledOrAlreadyFinalStatus(): void {
        $calculator = new OrderStateCalculator();

        self::assertSame(OrderStatus::Cancelled, $calculator->calculate(true, true, OrderStatus::Cancelled, true)->displayStatus);
        self::assertSame(OrderStatus::PaidAndDelivered, $calculator->calculate(true, true, OrderStatus::PaidAndDelivered, true)->displayStatus);
    }
}
