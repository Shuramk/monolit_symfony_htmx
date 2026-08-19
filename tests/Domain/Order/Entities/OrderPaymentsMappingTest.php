<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\Entities;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Entities\OrderPayment;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\UseCases\CreateOrder;
use App\Domain\Order\UseCases\UpdateOrder;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use App\Domain\Order\UseCases\Dtos\UpdateOrderDto;
use App\Domain\Order\UseCases\Dtos\UpdateOrderLineDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderPaymentsMappingTest extends KernelTestCase {
    public function testLockedLoadRefreshesPaymentsAndBlocksMarkupWithoutChangingThem(): void {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $customerId = (int) $connection->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
        $productId = (int) $connection->fetchOne('SELECT product.id FROM products product INNER JOIN products parent ON parent.id = product.parent_id WHERE product.enabled = 1 AND parent.enabled = 1 ORDER BY product.id LIMIT 1');
        $now = new \DateTimeImmutable('2026-10-07 12:16:03');
        $id = $container->get(CreateOrder::class)->execute(new CreateOrderDto($customerId, $now, null, null, '0', 0, [new CreateOrderLineDto($productId, '1', '100')]), 990001, $now);
        try {
            $order = $entityManager->find(Order::class, $id);
            self::assertFalse($order->hasPayments());
            foreach (['cash' => '100.00', 'shipment' => '0.00', 'refund' => '-100.00'] as $source => $amount) {
                $connection->insert('order_payments', [
                    'source' => $source, 'source_id' => $id, 'order_id' => $id,
                    'paid_at' => $now->format('Y-m-d H:i:s'), 'amount' => $amount,
                    'comment' => 'Проверка mapping', 'user_id' => null, 'shipment_id' => null,
                    'imported_at' => $now->format('Y-m-d H:i:s'),
                ]);
            }
            $before = $connection->fetchAllAssociative('SELECT * FROM order_payments WHERE order_id = ? ORDER BY source', [$id]);
            $repository = $container->get(OrderRepository::class);
            $repository->transactional(function () use ($repository, $id, $order): void {
                $loaded = $repository->findForUpdate($id);
                self::assertSame($order, $loaded);
                self::assertTrue($loaded->hasPayments());
                self::assertCount(1, $loaded->getItems());
                self::assertCount(3, (new \ReflectionProperty($loaded, 'payments'))->getValue($loaded));
            });
            $cash = $entityManager->find(OrderPayment::class, ['source' => 'cash', 'sourceId' => $id]);
            $shipment = $entityManager->find(OrderPayment::class, ['source' => 'shipment', 'sourceId' => $id]);
            self::assertNotSame($cash, $shipment);
            self::assertTrue($cash->isPositive());
            self::assertFalse($shipment->isPositive());
            $lineId = (int) $connection->fetchOne('SELECT id FROM order_items WHERE order_id = ?', [$id]);
            try {
                $container->get(UpdateOrder::class)->execute($id, new UpdateOrderDto(0, $now, [new UpdateOrderLineDto($productId, '1', '100', lineId: $lineId)], markup: 20), 990001, $now);
                self::fail('Expected paid order markup rejection.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('Нельзя менять БН', $exception->getMessage());
            }
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM order_payments WHERE order_id = ? ORDER BY source', [$id]));
            self::assertSame(0, (int) $connection->fetchOne('SELECT edit_version FROM orders WHERE id = ?', [$id]));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$id]));
        } finally {
            $connection->delete('orders', ['id' => $id]);
            $entityManager->clear();
        }
    }
}
