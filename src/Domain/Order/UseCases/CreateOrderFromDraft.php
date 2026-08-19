<?php

declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Application\Controller\Order\Dtos\OrderCustomerDto;
use App\Application\Controller\Order\Dtos\OrderDraftDto;
use App\Application\Middleware\RequestDtoParser;
use App\Domain\Order\Services\OrderDraftSession;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;

final readonly class CreateOrderFromDraft {
    public function __construct(
        private OrderDraftSession $draftSession,
        private RequestDtoParser $parser,
        private CreateOrder $createOrder,
    ) {
    }

    public function execute(OrderCustomerDto $customer, int $userId, \DateTimeImmutable $now): int {
        if (!$this->draftSession->hasLines()) {
            throw new \DomainException('Состав заказа отсутствует. Вернитесь к выбору продукции.');
        }

        $draft = $this->parser->parseArrayToDto($this->draftSession->load(), OrderDraftDto::class);
        $this->parser->validate($draft);
        assert($draft instanceof OrderDraftDto);

        $orderId = $this->createOrder->execute(new CreateOrderDto(
            customerId: $customer->customerId,
            desiredAt: $customer->desiredAt,
            shippingAddress: $customer->shippingAddress,
            comments: $customer->comments,
            shippingCost: $customer->shippingCost,
            markup: $draft->markup,
            lines: $draft->lines,
        ), $userId, $now);

        $this->draftSession->clear();

        return $orderId;
    }
}
