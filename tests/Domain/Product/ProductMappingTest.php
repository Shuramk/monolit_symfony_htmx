<?php declare(strict_types=1);

namespace App\Tests\Domain\Product;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Customer\Entities\Customer;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Entities\OrderItem;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use App\Domain\Order\UseCases\CreateOrder;
use App\Domain\Product\Entities\Product;
use App\Domain\Product\Repository\ProductRepository;
use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductMappingTest extends KernelTestCase {
    public function testOrderCreationLoadsAllProductsAndTheirParentsInOneQuery(): void {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $ids = array_map('intval', $connection->fetchFirstColumn('SELECT product.id FROM products product INNER JOIN products parent ON parent.id = product.parent_id WHERE product.enabled = 1 AND parent.enabled = 1 ORDER BY product.id LIMIT 2'));
        self::assertCount(2, $ids);
        $now = new \DateTimeImmutable('2026-10-08');
        $customerId = (int) $connection->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
        $dto = new CreateOrderDto($customerId, $now, null, null, '0', 0, [new CreateOrderLineDto($ids[0], '1'), new CreateOrderLineDto($ids[1], '2'), new CreateOrderLineDto($ids[0], '3')]);
        $debug = $container->get('doctrine.debug_data_holder');
        $debug->reset();
        $id = $container->get(CreateOrder::class)->execute($dto, 990001, $now);
        try {
            foreach ($dto->products as $product) {
                self::assertNotSame('', $product->getParent()->getName());
            }
            $queries = array_filter($debug->getData()['app'] ?? [], static fn (array $query): bool => preg_match('/\bFROM products\b/i', $query['sql']) === 1);
            self::assertCount(1, $queries);
            self::assertEqualsCanonicalizing($ids, array_keys($dto->products));
            self::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$id]));
        } finally {
            $connection->delete('orders', ['id' => $id]);
            $em->clear();
        }
    }

    public function testProductMappingMatchesTheExistingTableWithoutMigration(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager = $em->getConnection()->createSchemaManager();
        // Compare only Product, without application schema listeners (e.g. Messenger).
        $schemaEm = new EntityManager($em->getConnection(), $em->getConfiguration(), new EventManager());
        $schema = (new SchemaTool($schemaEm))->getSchemaFromMetadata([$schemaEm->getClassMetadata(Product::class)]);
        $diff = $manager->createComparator()->compareTables($manager->introspectTable('products'), $schema->getTable('products'));

        self::assertTrue($diff->isEmpty(), implode("\n", $em->getConnection()->getDatabasePlatform()->getAlterTableSQL($diff)));
    }

    public function testExistingCatalogAndOrderItemsHydrateAsRelatedEntities(): void {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $row = $connection->fetchAssociative('SELECT item.id AS item_id, product.id, product.parent_id, product.price, product.name FROM order_items item INNER JOIN products product ON product.id = item.product_id WHERE product.parent_id IS NOT NULL ORDER BY item.id LIMIT 1');
        self::assertIsArray($row);

        $product = $container->get(ProductRepository::class)->getById((int) $row['id']);
        self::assertInstanceOf(Product::class, $product);
        self::assertSame((int) $row['id'], $product->getId());
        self::assertSame($row['name'], $product->getName());
        self::assertSame($row['price'], $product->getPrice()->format());
        self::assertSame((int) $row['parent_id'], $product->getParent()->getId());
        self::assertTrue($product->getParent()->getVariants()->contains($product));
        $item = $em->find(OrderItem::class, (int) $row['item_id']);
        self::assertSame($product, $item->getProduct());
        self::assertSame($product->getId(), $item->getProductId());
    }

    public function testPersistingProductAndDeletingItPreservesOrderSnapshot(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $now = new \DateTimeImmutable('2026-10-08 12:34:56');
            $group = new Product(899001, 'Тестовая группа', 'м²', Decimal::zero(4), $now);
            $variant = new Product(899002, 'серый', 'м²', Decimal::fromString('435.1234', 4), $now, $group);
            $em->persist($group);
            $em->persist($variant);
            $em->flush();
            $customerId = (int) $connection->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
            $dto = new CreateOrderDto($customerId, $now, null, null, '0', 0, [new CreateOrderLineDto(899002, '2.500')]);
            $dto->products = [899002 => $variant];
            $order = Order::create(261008899, $em->find(Customer::class, $customerId), $dto, $now);
            $em->persist($order);
            $em->flush();
            $itemId = $order->getItems()[0]->getId();
            self::assertSame($variant, $order->getItems()[0]->getProduct());
            self::assertSame('435.1234', $connection->fetchOne('SELECT unit_price FROM order_items WHERE id = ?', [$itemId]));
            self::assertSame('2026-10-08 12:34:56', $connection->fetchOne('SELECT updated_at FROM products WHERE id = 899002'));

            $em->clear();
            $loaded = $em->find(Product::class, 899002);
            self::assertSame('435.1234', $loaded->getPrice()->format());
            self::assertSame(899001, $loaded->getParent()->getId());
            $em->remove($loaded);
            $em->flush();
            $em->clear();
            $item = $em->find(OrderItem::class, $itemId);
            self::assertNull($item->getProduct());
            self::assertNull($item->getProductId());
            self::assertSame(['id' => null, 'name' => 'серый', 'group_name' => 'Тестовая группа', 'unit' => 'м²'], $item->getProductSnapshot());
            self::assertSame('435.1234', $item->getUnitPrice()->format());
            self::assertSame('2.500', $item->getQuantity()->format());
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }

    public function testAvailabilityRulesAndBulkLookup(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $repository = self::getContainer()->get(ProductRepository::class);
        $row = $connection->fetchAssociative('SELECT product.id, product.parent_id FROM products product INNER JOIN products parent ON parent.id = product.parent_id WHERE product.enabled = 1 AND parent.enabled = 1 ORDER BY product.id LIMIT 1');
        self::assertIsArray($row);
        $id = (int) $row['id'];
        self::assertTrue($repository->getById($id)->isAvailableForOrder());
        self::assertSame([$id], array_keys($repository->getByIds([$id, $id, 999999999])));
        self::assertSame([], $repository->getByIds([]));
        $connection->beginTransaction();
        try {
            $connection->executeStatement('UPDATE products SET enabled = 0 WHERE id = ?', [$row['parent_id']]);
            $product = $repository->getByIds([$id])[$id];
            self::assertFalse($product->isAvailableForOrder());
            self::assertTrue($product->isAvailableForOrder(requireActiveParent: false));
            $connection->executeStatement('UPDATE products SET enabled = 0 WHERE id = ?', [$id]);
            $product = $repository->getByIds([$id])[$id];
            self::assertFalse($product->isAvailableForOrder(requireActiveParent: false));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }
}
