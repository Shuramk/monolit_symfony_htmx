<?php declare(strict_types=1);

namespace App\Domain\Order\Enums;

enum PaymentMoneyStatus: int {
    case Pending = 1;
    case Collected = 2;
    case InCashbox = 3;
}
