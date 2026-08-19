<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ReadModel\OrderListView;
use App\Domain\Order\ReadModel\Dtos\OrderSearchDto;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderListViewTest extends KernelTestCase {
    public function testSortingUsesAllowedColumnsAndPreservesPaginationAndFilters(): void {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $view = self::getContainer()->get(OrderListView::class);
        $statuses = array_column(OrderStatus::cases(), 'value');
        $columns = [
            'id' => 'id',
            'createdAt' => 'created_at',
            'desiredAt' => 'desired_at',
            'customer' => 'customer_name',
            'status' => "CASE status WHEN 'new' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'ready_for_shipment' THEN 3 WHEN 'delivered' THEN 4 WHEN 'partially_shipped' THEN 5 WHEN 'paid_and_delivered' THEN 6 WHEN 'cancelled' THEN 7 END",
        ];
        foreach ($columns as $sort => $column) {
            foreach (['ASC', 'DESC'] as $direction) {
                $result = $view->search('', $statuses, page: 2, sort: $sort, direction: $direction);
                $expected = $connection->fetchFirstColumn('SELECT id FROM orders ORDER BY '.$column.' '.$direction.($sort === 'id' ? '' : ', id DESC').' LIMIT 50 OFFSET 50');
                self::assertSame(array_map('intval', $expected), array_column($result->orders, 'id'), $sort.' '.$direction);
                self::assertSame($sort, $result->filterParams['sort']);
                self::assertSame($direction, $result->filterParams['direction']);
            }
        }
        $criteria = new OrderSearchDto(customer: 'А');
        $filtered = $view->search('', ['new'], criteria: $criteria, sort: 'customer', direction: 'asc');
        self::assertSame('ASC', $filtered->direction);
        self::assertSame(['new'], $filtered->filterParams['statuses']);
        self::assertSame(get_object_vars($criteria), $filtered->filterParams['search']);
        $invalid = $view->search('', $statuses, sort: 'id; DROP TABLE orders', direction: 'DESC; DELETE FROM orders');
        self::assertSame('id', $invalid->filterParams['sort']);
        self::assertSame('DESC', $invalid->direction);
    }

    public function testAdvancedFieldsAreCombinedAndClosedOrdersAreIncludedByDefault(): void {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $view = self::getContainer()->get(OrderListView::class);
        $order = $connection->fetchAssociative("SELECT id, customer_name, customer_phone, shipping_address FROM orders WHERE status = 'paid_and_delivered' AND shipping_address <> '' AND customer_phone <> '' ORDER BY id DESC LIMIT 1");
        self::assertIsArray($order);
        $criteria = new OrderSearchDto(orderId: (string) $order['id'], customer: $order['customer_name'], phone: $order['customer_phone'], address: $order['shipping_address']);
        $result = $view->search('', criteria: $criteria);
        self::assertSame([(int) $order['id']], array_column($result->orders, 'id'));
        self::assertCount(7, $result->statuses);
        self::assertSame(get_object_vars($criteria), $result->filterParams['search']);
        self::assertSame([], $view->search('', ['new'], criteria: $criteria)->orders);
        self::assertSame([], $view->search('', criteria: new OrderSearchDto(orderId: (string) $order['id'], address: 'nonexistent-address-xyz'))->orders);
        self::assertSame([], $view->search('', criteria: new OrderSearchDto(customer: "' OR 1=1 --"))->orders);
    }

    public function testProductGroupVariantAndApproximateQuantityUseOneOrderLine(): void {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $view = self::getContainer()->get(OrderListView::class);
        $line = $connection->fetchAssociative('SELECT item.product_id, item.quantity, product.parent_id FROM order_items item INNER JOIN products product ON product.id = item.product_id WHERE product.parent_id IS NOT NULL ORDER BY item.id LIMIT 1');
        self::assertIsArray($line);
        $quantity = (string) $line['quantity'];
        $productId = (int) $line['product_id'];
        $parentId = (int) $line['parent_id'];
        $criteria = new OrderSearchDto(parentId: $parentId, productId: $productId, quantity: $quantity);
        $result = $view->search('', criteria: $criteria);
        $expected = (int) $connection->fetchOne('SELECT COUNT(*) FROM orders WHERE EXISTS (SELECT 1 FROM order_items item WHERE item.order_id = orders.id AND item.product_id = ? AND item.quantity > CAST(? AS DECIMAL(14,3)) - 5 AND item.quantity < CAST(? AS DECIMAL(14,3)) + 5)', [$productId, $quantity, $quantity]);
        self::assertGreaterThan(0, $expected);
        self::assertSame($expected, $result->total);
        self::assertNotEmpty($result->productGroups);
        self::assertArrayHasKey($parentId, $result->productsByParent);
        $groupResult = $view->search('', criteria: new OrderSearchDto(parentId: $parentId));
        self::assertSame((int) $connection->fetchOne('SELECT COUNT(*) FROM orders WHERE EXISTS (SELECT 1 FROM order_items item INNER JOIN products product ON product.id = item.product_id WHERE item.order_id = orders.id AND product.parent_id = ?)', [$parentId]), $groupResult->total);
        $quantityResult = $view->search('', criteria: new OrderSearchDto(quantity: $quantity));
        self::assertSame((int) $connection->fetchOne('SELECT COUNT(*) FROM orders WHERE EXISTS (SELECT 1 FROM order_items item WHERE item.order_id = orders.id AND item.quantity > CAST(? AS DECIMAL(14,3)) - 5 AND item.quantity < CAST(? AS DECIMAL(14,3)) + 5)', [$quantity, $quantity]), $quantityResult->total);
    }

    public function testPaginationHasRealCountsAndNonOverlappingPages(): void {
        self::bootKernel();
        $view = self::getContainer()->get(OrderListView::class);
        $statuses = array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::cases());
        $first = $view->search('', $statuses);
        $second = $view->search('', $statuses, page: 2);
        $total = (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne('SELECT COUNT(*) FROM orders');

        self::assertSame($total, $first->total);
        self::assertSame((int) ceil($total / 50), $first->pages);
        self::assertCount(50, $first->orders);
        self::assertSame(2, $second->page);
        self::assertEmpty(array_intersect(array_column($first->orders, 'id'), array_column($second->orders, 'id')));
        self::assertSame($first->pages, $view->search('', $statuses, page: PHP_INT_MAX)->page);
        self::assertSame(1, $view->search('', $statuses, page: -5)->page);
    }

    public function testQuantityBoundsAreStrictAndAdditionalCustomerPhonesAreSearchable(): void {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $view = self::getContainer()->get(OrderListView::class);
        $row = $connection->fetchAssociative('SELECT orders.id, orders.customer_id, MIN(item.id) AS item_id FROM orders INNER JOIN order_items item ON item.order_id = orders.id WHERE orders.customer_id IS NOT NULL GROUP BY orders.id, orders.customer_id HAVING COUNT(*) = 1 LIMIT 1');
        self::assertIsArray($row);
        $connection->beginTransaction();
        try {
            $connection->executeStatement('UPDATE order_items SET quantity = 100 WHERE id = ?', [$row['item_id']]);
            foreach (['95', '105'] as $boundary) {
                self::assertSame([], $view->search('', criteria: new OrderSearchDto(orderId: (string) $row['id'], quantity: $boundary))->orders);
            }
            self::assertSame([(int) $row['id']], array_column($view->search('', criteria: new OrderSearchDto(orderId: (string) $row['id'], quantity: '104.999'))->orders, 'id'));
            $connection->executeStatement('UPDATE customers SET phone_secondary = ? WHERE id = ?', ['search-test-secondary-phone', $row['customer_id']]);
            self::assertSame([(int) $row['id']], array_column($view->search('', criteria: new OrderSearchDto(orderId: (string) $row['id'], phone: 'search-test-secondary-phone'))->orders, 'id'));
        } finally {
            $connection->rollBack();
        }
    }

    public function testDefaultAndExplicitStatusFilters(): void {
        self::bootKernel();
        $view = self::getContainer()->get(OrderListView::class);
        $default = $view->search('');
        self::assertSame(['new', 'in_progress', 'ready_for_shipment', 'delivered', 'partially_shipped'], array_map(static fn (OrderStatus $status): string => $status->value, $default->statuses));
        $paid = $view->search('', ['paid_and_delivered']);
        $total = (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne("SELECT COUNT(*) FROM orders WHERE status = 'paid_and_delivered'");
        self::assertSame($total, $paid->total);
        foreach ($paid->orders as $order) {
            self::assertSame(OrderStatus::PaidAndDelivered, $order->status);
        }
        self::assertSame(0, $view->search('', [''])->total);
        self::assertSame([], $view->search('', ['not-a-status'])->orders);
    }

    public function testManagerAndSearchFiltersUseApplicationData(): void {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $row = $connection->fetchAssociative('SELECT id, manager_id, status FROM orders WHERE manager_id IS NOT NULL ORDER BY id LIMIT 1');
        self::assertIsArray($row);
        $view = self::getContainer()->get(OrderListView::class);
        $page = $view->search((string) $row['id'], [$row['status']], [(string) $row['manager_id']]);

        self::assertContains((int) $row['id'], array_column($page->orders, 'id'));
        self::assertSame([(int) $row['manager_id']], $page->managerIds);
        self::assertContains((int) $row['manager_id'], array_map('intval', array_column($page->managerOptions, 'id')));
        self::assertSame($page->managerIds, $page->filterParams['managers']);
        $all = array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::cases());
        $managerPage = $view->search('', $all, [(int) $row['manager_id']]);
        self::assertSame((int) $connection->fetchOne('SELECT COUNT(*) FROM orders WHERE manager_id = ?', [$row['manager_id']]), $managerPage->total);
    }
}
