<?php

declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Application\Controller\Order\Dtos\OrderCustomerDto;
use App\Application\Controller\Order\Dtos\OrderDraftDto;
use App\Application\Middleware\RequestDtoParser;
use App\Domain\Order\Services\OrderDraftSession;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Warehouse\Repository\ShippingRepository;

final readonly class CreateOrderFromDraft {
    public function __construct(
        private OrderDraftSession $draftSession,
        private RequestDtoParser $parser,
        private CreateOrder $createOrder,
        private ShippingRepository $shipping,
    ) {
    }

    public function execute(OrderCustomerDto $customer, int $userId, \DateTimeImmutable $now): int {
        $now = $now->setTimezone(new \DateTimeZone('Europe/Kyiv'));
        if (!$this->draftSession->hasLines()) {
            throw new \DomainException('Состав заказа отсутствует. Вернитесь к выбору продукции.');
        }

        $draft = $this->parser->parseArrayToDto($this->draftSession->load(), OrderDraftDto::class);
        $this->parser->validate($draft);
        assert($draft instanceof OrderDraftDto);

        $selected = $this->draftSession->load()['customerId'] ?? null;
        if ($selected !== null && (int) $selected !== $customer->customerId) {
            throw new \DomainException('Заказчик изменился. Повторите выбор клиента.');
        }
        $shippingCost = $customer->shippingCost;
        $carrierId = null;
        $trips = null;
        $unitPrice = null;
        if ($customer->pickup !== null) {
            $shippingCost = '0.00';
            $trips = 0;
            $unitPrice = '0.00';
            if (!$customer->pickup) {
                if ($customer->carrierId < 1 || $customer->shippingTrips < 1) {
                    throw new \DomainException('Выберите перевозчика и укажите количество ходок.');
                }
                $this->shipping->requireCarrier($customer->carrierId);
                $carrierId = $customer->carrierId;
                $trips = $customer->shippingTrips;
                $unitPrice = Decimal::fromString($customer->shippingUnitPrice, 2)->format();
                $shippingCost = Decimal::fromString($unitPrice, 2)->multiplyByInt($trips)->format();
                if (Decimal::fromString($shippingCost, 2)->scaledValue() > 999999999999) {
                    throw new \DomainException('Стоимость доставки слишком велика.');
                }
            }
        }

        $orderId = $this->createOrder->execute(new CreateOrderDto(
            customerId: $customer->customerId,
            desiredAt: $customer->desiredAt,
            shippingAddress: $customer->shippingAddress,
            comments: $customer->comments,
            shippingCost: $shippingCost,
            markup: $draft->markup,
            lines: $draft->lines,
            carrierId: $carrierId,
            shippingTrips: $trips,
            shippingUnitPrice: $unitPrice,
        ), $userId, $now);

        $this->draftSession->clear();

        return $orderId;
    }
}
