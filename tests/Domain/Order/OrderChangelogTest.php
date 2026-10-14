<?php declare(strict_types=1);

namespace App\Tests\Domain\Order;

use App\Application\Command\ImportLegacyOrdersCommand;
use App\Domain\Customer\Repository\CustomerRepository;
use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Order\ReadModel\OrderFinancialCalculator;
use App\Domain\Order\Repository\OrderChangelogRepository;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\Services\OrderChangelog;
use App\Domain\Order\UseCases\CreateOrder;
use App\Domain\Order\UseCases\UpdateOrder;
use App\Domain\Order\UseCases\Dtos\UpdateOrderDto;
use App\Domain\Order\UseCases\Dtos\UpdateOrderLineDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class OrderChangelogTest extends KernelTestCase {
    private function activeProductId(Connection $connection): int {

        return (int) $connection->fetchOne('
            SELECT product.id FROM products product
            INNER JOIN products parent ON parent.id = product.parent_id
            WHERE product.enabled = 1 AND parent.enabled = 1
            ORDER BY product.id LIMIT 1
        ');
    }

    public function testLogFailureRollsBackEditedOrderAndVersion(): void {
        self::bootKernel();
        $container = self::getContainer();
        $connection = $container->get('doctrine.dbal.app_connection');
        $customerId = (int) $connection->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
        $productId = $this->activeProductId($connection);
        $now = new \DateTimeImmutable('2026-10-07');
        $id = $container->get(CreateOrder::class)->execute(new CreateOrderDto($customerId, $now, 'Старый адрес', null, '0', 0, [new CreateOrderLineDto($productId, '1', '100', '0')]), 990001, $now);
        try {
            $old = $connection->fetchAssociative('SELECT id, unit_price FROM order_items WHERE order_id = ?', [$id]);
            $failing = $this->createMock(Connection::class);
            $failing->method('fetchAssociative')->willReturnCallback(fn (...$args) => $connection->fetchAssociative(...$args));
            $failing->method('fetchAllAssociative')->willReturnCallback(fn (...$args) => $connection->fetchAllAssociative(...$args));
            $failing->expects(self::once())->method('insert')->willThrowException(new \RuntimeException('Journal write failed'));
            $useCase = new UpdateOrder($container->get(OrderRepository::class), new OrderChangelog(new OrderChangelogRepository($failing), new OrderFinancialCalculator()), $container->get(ProductRepository::class));
            $dto = new UpdateOrderDto(0, $now, [new UpdateOrderLineDto($productId, '2', '200', '0', (int) $old['id'])], shippingAddress: 'Новый адрес');
            try {
                $useCase->execute($id, $dto, 990001, $now);
                self::fail('Expected journal failure.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Journal write failed', $exception->getMessage());
            }
            self::assertSame($old, $connection->fetchAssociative('SELECT id, unit_price FROM order_items WHERE order_id = ?', [$id]));
            self::assertSame('Старый адрес', $connection->fetchOne('SELECT shipping_address FROM orders WHERE id = ?', [$id]));
            self::assertSame(0, (int) $connection->fetchOne('SELECT edit_version FROM orders WHERE id = ?', [$id]));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$id]));
            $container->get(UpdateOrder::class)->execute($id, $dto, 990001, $now);
            self::assertSame(1, (int) $connection->fetchOne('SELECT edit_version FROM orders WHERE id = ?', [$id]));
            self::assertSame('2.000', $connection->fetchOne('SELECT quantity FROM order_items WHERE order_id = ?', [$id]));
            self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$id]));
        } finally {
            $connection->delete('orders', ['id' => $id]);
        }
    }

    public function testLogFailureRollsBackOrderAndItems(): void {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $customerId = (int) $connection->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
        $productId = $this->activeProductId($connection);
        $before = $connection->fetchFirstColumn('SELECT id FROM orders ORDER BY id');
        $itemsBefore = $connection->fetchOne('SELECT COUNT(*) FROM order_items');
        $failingConnection = $this->createMock(Connection::class);
        $failingConnection->method('fetchAssociative')->willReturn([
            'id' => 1, 'customer_id' => $customerId, 'customer_name' => 'Клиент', 'customer_phone' => null,
            'shipping_address' => null, 'shipping_cost' => '0.00', 'desired_at' => '2026-10-07 00:00:00',
            'comments' => null, 'markup' => 0, 'status' => 'new',
        ]);
        $failingConnection->method('fetchAllAssociative')->willReturn([]);
        $failingConnection->expects(self::once())->method('insert')->willThrowException(new \RuntimeException('Journal write failed'));
        $useCase = new CreateOrder(
            self::getContainer()->get(OrderRepository::class),
            new OrderChangelog(new OrderChangelogRepository($failingConnection), new OrderFinancialCalculator()),
            self::getContainer()->get(CustomerRepository::class),
            self::getContainer()->get(ProductRepository::class),
        );
        $order = new CreateOrderDto($customerId, new \DateTimeImmutable('2026-10-07'), null, null, '0', 0, [new CreateOrderLineDto($productId, '1', '100', '0')]);

        try {
            $useCase->execute($order, 990001, new \DateTimeImmutable('2026-10-07'));
            self::fail('Expected the journal error.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Journal write failed', $exception->getMessage());
        }

        self::assertSame($before, $connection->fetchFirstColumn('SELECT id FROM orders ORDER BY id'));
        self::assertSame($itemsBefore, $connection->fetchOne('SELECT COUNT(*) FROM order_items'));
        self::assertFalse($connection->isTransactionActive());
    }

    public function testSnapshotDoesNotChangeWithOrderAndEscapesHtml(): void {
        self::bootKernel();
        $container = self::getContainer();
        $connection = $container->get('doctrine.dbal.app_connection');
        $customerId = (int) $connection->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
        $productId = $this->activeProductId($connection);
        $now = new \DateTimeImmutable('2026-10-07 12:00:00');
        $dto = new CreateOrderDto($customerId, $now, '<script>alert(1)</script>', 'Комментарий', '0', 0, [new CreateOrderLineDto($productId, '12.500', '370', '0')]);
        $dto->products = [$productId => ['id' => $productId, 'name' => 'Подменённый товар', 'group_name' => null, 'unit' => 'шт', 'price' => '0']];
        $orderId = $container->get(CreateOrder::class)->execute($dto, 990001, $now);
        try {
            $product = $container->get(ProductRepository::class)->getById($productId);
            self::assertSame([$productId => $product], $dto->products);
            self::assertSame($product->getName(), $connection->fetchOne('SELECT product_name FROM order_items WHERE order_id = ?', [$orderId]));
            $stored = $connection->fetchOne('SELECT snapshot FROM order_changelog WHERE order_id = ?', [$orderId]);
            $import = new CommandTester($container->get(ImportLegacyOrdersCommand::class));
            self::assertSame(Command::FAILURE, $import->execute([]));
            self::assertStringContainsString('Повторный импорт остановлен', $import->getDisplay());
            self::assertNotFalse($connection->fetchOne('SELECT id FROM orders WHERE id = ?', [$orderId]));
            $connection->update('orders', ['shipping_address' => 'Новый адрес'], ['id' => $orderId]);
            $connection->update('order_items', ['product_name' => 'Новое имя', 'unit_price' => '1'], ['order_id' => $orderId]);
            self::assertSame($stored, $connection->fetchOne('SELECT snapshot FROM order_changelog WHERE order_id = ?', [$orderId]));
            $snapshot = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('4625.00', $snapshot['total']);
            $html = $container->get('twig')->render('order/_changelog_snapshot.html.twig', ['snapshot' => $snapshot]);
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringContainsString('&lt;script&gt;', $html);
            self::assertStringNotContainsString('Новое имя', $html);

            $connection->transactional(fn () => $container->get(OrderChangelog::class)->record($orderId, 'updated', 'Заказ изменён', 990001, $now));
            self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$orderId]));
            self::assertSame($stored, $connection->fetchOne("SELECT snapshot FROM order_changelog WHERE order_id = ? AND event = 'created'", [$orderId]));
        } finally {
            $connection->executeStatement('DELETE FROM orders WHERE id = ?', [$orderId]);
        }
    }
}
