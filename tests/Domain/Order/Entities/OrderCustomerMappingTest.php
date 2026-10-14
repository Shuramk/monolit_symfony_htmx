<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\Entities;

use App\Domain\Customer\Repository\CustomerRepository;
use App\Domain\Order\Entities\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderCustomerMappingTest extends KernelTestCase {
    public function testImportedOrderReferencesTheExistingCustomer(): void {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $row = $entityManager->getConnection()->fetchAssociative('SELECT id, customer_id FROM orders WHERE customer_id IS NOT NULL ORDER BY id LIMIT 1');
        self::assertIsArray($row);

        $order = $entityManager->find(Order::class, (int) $row['id']);
        $customer = self::getContainer()->get(CustomerRepository::class)->getById((int) $row['customer_id']);

        self::assertSame($customer, $order->getCustomer());
        self::assertSame((int) $row['customer_id'], $customer->getId());
        self::assertNotSame('', $customer->getName());
    }

    public function testOrderWithoutCustomerKeepsItsHistoricalSnapshot(): void {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $row = $connection->fetchAssociative('SELECT id, customer_name, customer_phone FROM orders ORDER BY id LIMIT 1');
        self::assertIsArray($row);
        $connection->beginTransaction();
        try {
            $connection->update('orders', ['customer_id' => null], ['id' => $row['id']]);
            $entityManager->clear();
            $order = $entityManager->find(Order::class, (int) $row['id']);

            self::assertNull($order->getCustomer());
            $original = $entityManager->getUnitOfWork()->getOriginalEntityData($order);
            self::assertSame($row['customer_name'], $original['customerName']);
            self::assertSame($row['customer_phone'], $original['customerPhone']);
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
