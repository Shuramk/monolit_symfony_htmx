<?php declare(strict_types=1);

namespace App\Domain\Order\Enums;

enum OrderListSort: string {
    case Id = 'id';
    case CreatedAt = 'createdAt';
    case DesiredAt = 'desiredAt';
    case Customer = 'customer';
    case Status = 'status';
}
