<?php

declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Domain\Customer\Repository\CustomerRepository;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\Services\OrderChangelog;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Product\Repository\ProductRepository;

final readonly class CreateOrder {
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderChangelog $changelog,
        private CustomerRepository $customers,
        private ProductRepository $products,
    ) {}

    public function execute(CreateOrderDto $order, int $userId, \DateTimeImmutable $now): int {
        return $this->orderRepository->transactional(function () use ($order, $userId, $now): int {
            $customer = $this->customers->getById($order->customerId);
            $order->products = $this->products->getByIds(array_column($order->lines, 'productId'));
            $entity = Order::create(
                id: $this->orderRepository->nextOrderId($now),
                customer: $customer,
                data: $order,
                now: $now,
                managerId: $userId,
            );
            $this->orderRepository->save($entity);
            $orderId = $entity->getId();
            $this->changelog->created($orderId, $order, $userId, $now);

            return $orderId;
        });
    }
}
