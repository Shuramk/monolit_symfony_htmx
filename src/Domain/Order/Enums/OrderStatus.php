<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

enum OrderStatus: string {
    case New = "new";
    case InProgress = "in_progress";
    case ReadyForShipment = "ready_for_shipment";
    case Delivered = "delivered";
    case PartiallyShipped = "partially_shipped";
    case PaidAndDelivered = "paid_and_delivered";
    case Cancelled = "cancelled";

    public function title(): string {
        return match ($this) {
            self::New => "Новый",
            self::InProgress => "В работе",
            self::ReadyForShipment => "Готов к отгрузке",
            self::Delivered => "Доставлен",
            self::PartiallyShipped => "Частично отгружен",
            self::PaidAndDelivered => "Оплачен и доставлен",
            self::Cancelled => "Отменен",
        };
    }
}
