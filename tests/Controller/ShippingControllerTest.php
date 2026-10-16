<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShippingControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testCreateCollectEditCompleteAndArchive(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/orders/99009401/shipping/new');
            self::assertResponseIsSuccessful();
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('form [name="_token"]')->attr('value')];
            $payload = [
                'version' => 0, 'operationId' => '019a0b6c-1111-7000-8000-000000000001',
                'date' => '2026-10-20', 'time' => '14:30', 'storage' => 1,
                'comment' => 'Тест отгрузки', 'lines' => [['productId' => $product, 'quantity' => '1.250']],
            ];
            $client->jsonRequest('POST', '/orders/99009401/shipping', $payload);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', '/orders/99009401/shipping', $payload, $headers);
            self::assertResponseIsSuccessful();
            $taskId = json_decode($client->getResponse()->getContent(), true)['id'];
            $client->jsonRequest('POST', '/orders/99009401/shipping', $payload, $headers);
            self::assertResponseIsSuccessful();
            self::assertSame($taskId, json_decode($client->getResponse()->getContent(), true)['id']);
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/collect', ['version' => 0], $headers);
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/collect', ['version' => 1], $headers);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) $db->fetchOne('SELECT version FROM warehouse_shipping_tasks WHERE id=:id', ['id' => $taskId]));
            $client->jsonRequest('POST', '/shipping/' . $taskId, array_replace($payload, ['version' => 1]), $headers);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) $db->fetchOne('SELECT version FROM warehouse_shipping_tasks WHERE id=:id', ['id' => $taskId]));
            $client->jsonRequest('POST', '/shipping/' . $taskId, array_replace($payload, [
                'version' => 1, 'comment' => 'Изменено',
            ]), $headers);
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/complete', ['version' => 1], $headers);
            self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/complete', ['version' => 2], $headers);
            self::assertResponseIsSuccessful();
            self::assertSame('8.750', $db->fetchOne('SELECT storage_office FROM products WHERE id=:id', ['id' => $product]));
            self::assertSame('20.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=:id', ['id' => $product]));
            self::assertSame('partially_shipped', $db->fetchOne('SELECT status FROM orders WHERE id=99009401'));
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/complete', ['version' => 2], $headers);
            self::assertResponseStatusCodeSame(409);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM order_shipments WHERE task_id=:id', ['id' => $taskId]));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM warehouse_history WHERE task_id=:id', ['id' => $taskId]));
            $client->request('GET', '/shipping/archive?q=' . $taskId);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.shipping-list', 'Изменено');
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/delete', ['version' => 3], $headers);
            self::assertResponseStatusCodeSame(409);
            $remainingPayload = array_replace($payload, [
                'version' => (int) $db->fetchOne('SELECT edit_version FROM orders WHERE id=99009401'),
                'operationId' => '019a0b6c-1111-7000-8000-000000000004',
                'storage' => 2, 'lines' => [['productId' => $product, 'quantity' => '2.750']],
            ]);
            $client->jsonRequest('POST', '/orders/99009401/shipping', $remainingPayload, $headers);
            self::assertResponseIsSuccessful();
            $remainingId = json_decode($client->getResponse()->getContent(), true)['id'];
            $client->jsonRequest('POST', '/shipping/' . $remainingId . '/complete', ['version' => 0], $headers);
            self::assertResponseIsSuccessful();
            self::assertSame('paid_and_delivered', $db->fetchOne('SELECT status FROM orders WHERE id=99009401'));
            self::assertSame('17.250', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=:id', ['id' => $product]));
            self::assertSame('8.750', $db->fetchOne('SELECT storage_office FROM products WHERE id=:id', ['id' => $product]));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testRejectsExcessQuantityForeignProductsAndCancelledOrders(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/orders/99009401/shipping/new');
            self::assertResponseIsSuccessful();
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('form [name="_token"]')->attr('value')];
            foreach (['4.001', '-1', 'abc', '0', [], true] as $quantity) {
                $client->jsonRequest('POST', '/orders/99009401/shipping', [
                    'version' => 0, 'operationId' => '019a0b6c-1111-7000-8000-000000000002',
                    'date' => '2026-10-20', 'lines' => [['productId' => $product, 'quantity' => $quantity]],
                ], $headers);
                self::assertResponseStatusCodeSame(400);
            }
            $client->jsonRequest('POST', '/orders/99009401/shipping', [
                'version' => 0, 'operationId' => '019a0b6c-1111-7000-8000-000000000005',
                'date' => '2026-10-20', 'lines' => [['productId' => 999999999, 'quantity' => '1']],
            ], $headers);
            self::assertResponseStatusCodeSame(400);
            $db->update('orders', ['status' => 'cancelled'], ['id' => 99009401]);
            $client->jsonRequest('POST', '/orders/99009401/shipping', [
                'version' => 0, 'operationId' => '019a0b6c-1111-7000-8000-000000000003',
                'date' => '2026-10-20', 'lines' => [['productId' => $product, 'quantity' => '1']],
            ], $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM warehouse_shipping_tasks WHERE order_id=99009401'));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testApprovalChangesVersionAndDeletionReleasesReservationWithoutChangingStock(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/orders/99009401/shipping/new');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('form [name="_token"]')->attr('value')];
            $payload = [
                'version' => 0, 'operationId' => '019a0b6c-1111-7000-8000-000000000006',
                'date' => '2026-10-20', 'lines' => [['productId' => $product, 'quantity' => '4']],
            ];
            $client->jsonRequest('POST', '/orders/99009401/shipping', $payload, $headers);
            self::assertResponseIsSuccessful();
            $taskId = json_decode($client->getResponse()->getContent(), true)['id'];
            $client->jsonRequest('POST', '/orders/99009401/shipping', array_replace($payload, [
                'version' => 1, 'operationId' => '019a0b6c-1111-7000-8000-000000000007',
            ]), $headers);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST', '/crm/shipments/' . $taskId . '/approval', [
                'allowed' => 1, 'expectedAllowed' => 0, 'returnToShipping' => true,
            ], $headers);
            self::assertResponseIsSuccessful();
            self::assertSame('/shipping', json_decode($client->getResponse()->getContent(), true)['location']);
            self::assertSame(1, (int) $db->fetchOne('SELECT version FROM warehouse_shipping_tasks WHERE id=:id', ['id' => $taskId]));
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/complete', ['version' => 0], $headers);
            self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/delete', ['version' => 1], $headers);
            self::assertResponseIsSuccessful();
            self::assertSame('20.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=:id', ['id' => $product]));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM order_shipments WHERE task_id=:id', ['id' => $taskId]));
            $client->request('GET', '/shipping?q=' . $taskId);
            self::assertSelectorNotExists('#task-' . $taskId);
            $client->request('GET', '/orders/99009401/shipping/new');
            self::assertSelectorExists('input[type="number"][value="4.000"]');
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testFailureOnSecondProductRollsBackStockShipmentAndTask(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $second = (int) $db->fetchOne('
                SELECT p.id FROM products p JOIN products g ON g.id = p.parent_id
                WHERE p.enabled = 1 AND g.enabled = 1 AND p.id > :id ORDER BY p.id LIMIT 1
            ', ['id' => $product]);
            $db->update('products', ['storage_workshop' => '10'], ['id' => $second]);
            $db->insert('order_items', [
                'order_id' => 99009401, 'product_id' => $second, 'product_name' => 'Вторая позиция',
                'unit' => 'м²', 'quantity' => '1', 'unit_price' => '0', 'position' => 2,
            ]);
            $crawler = $client->request('GET', '/orders/99009401/shipping/new');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('form [name="_token"]')->attr('value')];
            $client->jsonRequest('POST', '/orders/99009401/shipping', [
                'version' => 0, 'operationId' => '019a0b6c-1111-7000-8000-000000000008',
                'date' => '2026-10-20', 'lines' => [
                    ['productId' => $product, 'quantity' => '1'],
                    ['productId' => $second, 'quantity' => '1'],
                ],
            ], $headers);
            self::assertResponseIsSuccessful();
            $taskId = json_decode($client->getResponse()->getContent(), true)['id'];
            $db->update('products', ['parent_id' => null], ['id' => $second]);
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/complete', ['version' => 0], $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('20.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=:id', ['id' => $product]));
            self::assertSame('10.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=:id', ['id' => $second]));
            self::assertSame(0, (int) $db->fetchOne('SELECT status FROM warehouse_shipping_tasks WHERE id=:id', ['id' => $taskId]));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM order_shipments WHERE task_id=:id', ['id' => $taskId]));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM warehouse_history WHERE task_id=:id', ['id' => $taskId]));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testSalesCannotPerformShipmentEvenWithPermissionAndListNeedsItsPermission(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $client->request('GET', '/shipping');
        self::assertResponseStatusCodeSame(403);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/orders/99009401/shipping/new');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('form [name="_token"]')->attr('value')];
            $client->jsonRequest('POST', '/orders/99009401/shipping', [
                'version' => 0, 'operationId' => '019a0b6c-1111-7000-8000-000000000009',
                'date' => '2026-10-20', 'lines' => [['productId' => $product, 'quantity' => '1']],
            ], $headers);
            self::assertResponseIsSuccessful();
            $taskId = json_decode($client->getResponse()->getContent(), true)['id'];
            $db->update('identity_users', ['role' => 'ROLE_SALES'], ['id' => 990001]);
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
            $this->authenticate($client);
            $crawler = $client->request('GET', '/shipping?q=' . $taskId);
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('form[action$="/complete"]'));
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('form [name="_token"]')->first()->attr('value')];
            $client->jsonRequest('POST', '/shipping/' . $taskId . '/complete', ['version' => 0], $headers);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('20.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=:id', ['id' => $product]));
            $db->executeStatement("UPDATE warehouse_shipping_tasks SET payload = JSON_SET(payload, '$.storage', 1) WHERE id=:id", ['id' => $taskId]);
            $db->update('identity_users', ['role' => 'ROLE_PRODUCTION'], ['id' => 990001]);
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
            $this->authenticate($client);
            $client->request('GET', '/shipping?q=' . $taskId);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('#task-' . $taskId);
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testNativeFormSavesAndUnpaidCompletionUsesCurrentPrice(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/orders/99009401/shipping/new');
            self::assertResponseIsSuccessful();
            $values = $crawler->filter('.shipping-form')->form()->getPhpValues();
            self::assertNotEmpty($values['operationId']);
            $client->jsonRequest('POST', '/orders/99009401/shipping', $values, ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(), true)['id'];
            $crawler = $client->request('GET', '/shipping/' . $id . '/edit');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.shipping-form input[value="4.000"]');
            $db->update('order_items', ['unit_price' => '25.0000'], ['order_id' => 99009401]);
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
            $client->jsonRequest('POST', '/shipping/' . $id . '/complete', ['version' => 0], ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseIsSuccessful();
            self::assertSame('delivered', $db->fetchOne('SELECT status FROM orders WHERE id=99009401'));
            self::assertSame('25.0000', $db->fetchOne('SELECT price FROM warehouse_shipping_items WHERE task_id=:id', ['id' => $id]));
            self::assertSame('16.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=:id', ['id' => $product]));
            $client->request('GET', '/shipping/archive?page=999999');
            self::assertResponseIsSuccessful();
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testLegacyLayoutAndPrintFormsUseOnlyTaskQuantities(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $db->insert('warehouse_shipping_tasks', [
                'id' => 99009490, 'order_id' => 99009401, 'type' => 2, 'status' => 0,
                'performance_date' => date('Y-m-d') . ' 14:30:00',
                'comment' => 'Вид таблицы', 'payload' => '{"storage":2,"collected":1}',
            ]);
            $db->insert('warehouse_shipping_items', [
                'task_id' => 99009490, 'product_id' => $product, 'quantity' => '1.250', 'price' => '20',
            ]);
            $db->update('order_items', ['unit_price' => '20'], ['order_id' => 99009401]);
            $parent = (int) $db->fetchOne('SELECT parent_id FROM products WHERE id=:id', ['id' => $product]);
            $db->update('products', ['pallets' => '1;1;0.5;2;1;0.5'], ['id' => $parent]);
            $client->request('GET', '/shipping?q=99009490');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('#task-99009490.shipping-due-today');
            self::assertSelectorTextContains('.shipping-documents', 'Накладная(с ценами)');
            foreach (['invoice', 'priced', 'security'] as $kind) {
                $client->request('GET', '/shipping/99009490/print/' . $kind);
                self::assertResponseIsSuccessful();
                self::assertSelectorTextContains('.shipping-print-items', '1.250');
                self::assertSelectorTextNotContains('.shipping-print-items', '4.000');
                if ($kind === 'priced') {
                    self::assertSelectorTextContains('.shipping-print-items', '25.00');
                } else {
                    self::assertSelectorNotExists('.shipping-print-price');
                }
                if ($kind === 'security') {
                    self::assertSelectorTextContains('.shipping-print-pallets', '0.5');
                }
            }
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testDateOnlyEditWithZeroOrderedQuantityPreservesTaskItemsAndPrices(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $db->update('order_items', ['quantity' => '0', 'unit_price' => '37.50'], ['order_id' => 99009401]);
            $db->insert('order_shipments', ['id' => 99009491, 'order_id' => 99009401, 'imported_at' => '2026-10-15 10:00:00']);
            $db->insert('order_shipment_items', ['shipment_id' => 99009491, 'product_id' => $product, 'quantity' => '-1']);
            $db->insert('warehouse_shipping_tasks', [
                'id' => 99009491, 'order_id' => 99009401, 'type' => 2, 'status' => 0,
                'performance_date' => '2026-10-20 14:30:00', 'comment' => 'Старая задача',
                'payload' => '{"storage":2,"allowed":1,"collected":1}',
            ]);
            $db->insert('warehouse_shipping_items', ['task_id' => 99009491, 'product_id' => $product, 'quantity' => '1', 'price' => '12.34']);
            $items = $db->fetchAllAssociative('SELECT * FROM warehouse_shipping_items WHERE task_id=99009491');
            $crawler = $client->request('GET', '/shipping/99009491/edit');
            self::assertResponseIsSuccessful();
            $values = $crawler->filter('.shipping-form')->form()->getPhpValues();
            $values['date'] = '2026-10-22';
            $client->jsonRequest('POST', '/shipping/99009491', $values, ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->headers->get('X-Debug-Exception', ''));
            self::assertSame('2026-10-22 14:30:00', $db->fetchOne('SELECT performance_date FROM warehouse_shipping_tasks WHERE id=99009491'));
            self::assertSame($items, $db->fetchAllAssociative('SELECT * FROM warehouse_shipping_items WHERE task_id=99009491'));
            self::assertSame('0.000', $db->fetchOne('SELECT quantity FROM order_items WHERE order_id=99009401'));
            self::assertSame('37.5000', $db->fetchOne('SELECT unit_price FROM order_items WHERE order_id=99009401'));
            $payload = json_decode($db->fetchOne('SELECT payload FROM warehouse_shipping_tasks WHERE id=99009491'), true);
            self::assertSame(1, $payload['allowed']);
            self::assertSame(1, $payload['collected']);
            $client->jsonRequest('POST', '/shipping/99009491', array_replace($values, ['version' => 1]), ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) $db->fetchOne('SELECT version FROM warehouse_shipping_tasks WHERE id=99009491'));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testCreateFormHasSeparateStockWeightAndReservationDetails(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $db->insert('shipping_carriers', ['id' => 99009492, 'name' => 'Тест перевозчик', 'payload' => '{}']);
            $parent = (int) $db->fetchOne('SELECT parent_id FROM products WHERE id=:id', ['id' => $product]);
            $db->update('products', ['weight_per_unit' => '13'], ['id' => $parent]);
            $db->insert('warehouse_shipping_tasks', [
                'id' => 99009492, 'order_id' => 99009401, 'type' => 2, 'status' => 0,
                'performance_date' => '2026-10-20 14:30:00', 'payload' => '{"storage":2}',
            ]);
            $db->insert('warehouse_shipping_items', ['task_id' => 99009492, 'product_id' => $product, 'quantity' => '1', 'price' => '0']);
            $crawler = $client->request('GET', '/orders/99009401/shipping/new');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.shipping-stock-overview');
            self::assertSelectorExists('.shipping-quantity-table');
            self::assertSelectorTextContains('[data-shipping-form-target="totalWeight"]', '0.039т');
            self::assertSelectorExists('.shipping-stock-overview a[href="/orders/99009401"]');
            self::assertSelectorExists('input[name="pickup"]');
            self::assertSelectorExists('input[name="allowed"]');
            $values = $crawler->filter('.shipping-form')->form()->getPhpValues();
            $values['allowed'] = '1';
            $values['pickup'] = '1';
            $values['carrierId'] = '99009492';
            $values['shippingPrice'] = '25';
            $client->jsonRequest('POST', '/orders/99009401/shipping', $values, ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(), true)['id'];
            $payload = json_decode($db->fetchOne('SELECT payload FROM warehouse_shipping_tasks WHERE id=:id', ['id' => $id]), true);
            self::assertSame(1, $payload['allowed']);
            self::assertNull($payload['carrierID']);
            self::assertSame('0.00', $payload['shipmentPrice']);
            $history = $db->fetchAssociative('
                SELECT changed_at, user_id, comment FROM order_status_history WHERE task_id = :id
            ', ['id' => $id]);
            self::assertNotFalse($history, 'Initially approved tasks must record approval in the order history.');
            self::assertSame('Согласована задача на отгрузку №' . $id, $history['comment']);
            self::assertSame(990001, (int) $history['user_id']);
            self::assertSame($db->fetchOne(
                'SELECT created_at FROM warehouse_shipping_tasks WHERE id = :id', ['id' => $id],
            ), $history['changed_at']);
            $client->jsonRequest('POST', '/orders/99009401/shipping', $values, ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) $db->fetchOne(
                'SELECT COUNT(*) FROM order_status_history WHERE task_id = :id', ['id' => $id],
            ));
            $client->request('GET', '/orders/99009401');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.legacy-order-history', 'Согласована задача на отгрузку №' . $id);
            $db->executeStatement('
                DELETE access FROM identity_user_permissions access
                INNER JOIN identity_permissions permission ON permission.id = access.permission_id
                WHERE access.user_id = 990001 AND permission.legacy_division_id = 1700
            ');
            $client->jsonRequest('POST', '/orders/99009401/shipping', $values, ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseStatusCodeSame(403);
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testCollectionAndApprovalPreserveSearchAndPageFromTheActualForms(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $query = 'Фильтр & тест';
            for ($index = 0; $index < 101; ++$index) {
                $db->insert('warehouse_shipping_tasks', [
                    'id' => 99009500 + $index, 'order_id' => 99009401, 'type' => 2,
                    'status' => 0, 'comment' => $query, 'payload' => '{"storage":2}',
                    'performance_date' => '2026-10-20 14:30:00',
                ]);
            }
            $db->insert('warehouse_shipping_items', [
                'task_id' => 99009600, 'product_id' => $product, 'quantity' => '1', 'price' => '0',
            ]);
            $location = '/shipping?q=' . rawurlencode($query) . '&page=3';
            foreach (['collect', 'approval', 'approval'] as $index => $action) {
                $crawler = $client->request('GET', $location);
                self::assertResponseIsSuccessful();
                $form = $crawler->filter('#task-99009600 form[action*="/' . $action . '"]')->form();
                parse_str((string) parse_url($form->getUri(), PHP_URL_QUERY), $formQuery);
                self::assertSame($query, $formQuery['q'] ?? null);
                self::assertSame('3', $formQuery['page'] ?? null);
                $values = $form->getPhpValues();
                $client->jsonRequest('POST', $form->getUri(), $values, ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
                self::assertResponseIsSuccessful();
                $response = json_decode($client->getResponse()->getContent(), true);
                self::assertSame([
                    'id' => 99009600, 'version' => $index + 1, 'status' => 0,
                    'collected' => true, 'allowed' => $index === 1,
                ], $response['task'] ?? null);
                $location = $response['location'];
                self::assertSame('/shipping', parse_url($location, PHP_URL_PATH));
                parse_str((string) parse_url($location, PHP_URL_QUERY), $returnQuery);
                self::assertSame(['q' => $query, 'page' => '3'], $returnQuery);
            }
            $client->request('GET', $location);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('#task-99009600[data-controller="shipping-status"]');
            self::assertSelectorExists('#task-99009600 .shipping-ready-check');
            self::assertSelectorNotExists('#task-99009600 [data-shipping-status-target="collectButton"]');
            self::assertSelectorTextContains('#task-99009600 .shipping-approval summary', 'Согласовано: —');
            $client->jsonRequest('POST', '/shipping/99009600/collect', ['version' => 0], ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST', '/shipping/99009600/uncollect', ['version' => 3], ['HTTP_X_CSRF_TOKEN' => $values['_token']]);
            self::assertResponseStatusCodeSame(404);
            self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM order_changelog WHERE order_id = 99009401 AND event = 'shipping_uncollect'"));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testShippingHistoryUsesKyivTimeEvenWhenPhpAndInputUseUtc(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $shipping = self::getContainer()->get(\App\Domain\Warehouse\UseCases\ShippingTasks::class);
            $utc = new \DateTimeZone('UTC');
            $id = $shipping->create(99009401, new \App\Domain\Warehouse\UseCases\Dtos\ShippingTaskDto(
                version: 0,
                operationId: '019a0b6c-1111-7000-8000-000000000099',
                date: '2026-10-20',
                time: '14:30',
                lines: [['productId' => $product, 'quantity' => '1']],
            ), 990001, new \DateTimeImmutable('2026-10-15 11:56:45', $utc));
            $shipping->act($id, 0, 'collect', 990001, true, new \DateTimeImmutable('2026-10-15 11:57:01', $utc));
            self::getContainer()->get(\App\Domain\Crm\UseCases\ApproveShipment::class)->execute(
                $id, true, false, 990001,
                new \DateTimeImmutable('2026-10-15 14:57:21', new \DateTimeZone('Europe/Kyiv')),
            );
            $shipping->act($id, 2, 'delete', 990001, true, new \DateTimeImmutable('2026-10-15 13:26:49', $utc));
            $times = $db->fetchAllKeyValue('
                SELECT event, created_at FROM order_changelog WHERE order_id = 99009401 ORDER BY id
            ');
            self::assertSame([
                'shipping_created' => '2026-10-15 14:56:45',
                'shipping_collect' => '2026-10-15 14:57:01',
                'shipping_delete' => '2026-10-15 16:26:49',
            ], $times);
            $history = self::getContainer()->get(\App\Domain\Order\Repository\OrderViewRepository::class)->statusHistory(99009401);
            self::assertStringContainsString('Удалена', $history[0]['changelog']);
            self::assertStringContainsString('Согласована', $history[1]['changelog']);
            self::assertStringContainsString('Собрана', $history[2]['changelog']);
            self::assertSame('2026-10-15 14:56:45', $db->fetchOne(
                'SELECT created_at FROM warehouse_shipping_tasks WHERE id = :id', ['id' => $id],
            ));
            self::assertSame('2026-10-20 14:30:00', $db->fetchOne(
                'SELECT performance_date FROM warehouse_shipping_tasks WHERE id = :id', ['id' => $id],
            ));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testHistoryTimezoneRepairIsRepeatableAndPreservesLegacyAndScheduledDates(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->fixture($db);
            $db->insert('warehouse_shipping_tasks', [
                'id' => 99009901, 'order_id' => 99009401, 'type' => 2, 'status' => 0,
                'created_at' => '2026-10-15 11:56:45',
                'performance_date' => '2026-10-20 14:30:00',
                'operation_key' => 'shipping:timezone-repair-test',
            ]);
            $ids = [];
            foreach ([
                ['shipping_created', '2026-10-15 11:56:45', ['taskId' => 99009901]],
                ['shipping_collect', '2026-01-15 11:57:01', ['taskId' => 99009901]],
                ['shipping_delete', '2026-10-15 16:26:49', ['taskId' => 99009901, 'timezone' => 'Europe/Kyiv']],
                ['updated', '2026-10-15 11:00:00', []],
            ] as [$event, $date, $context]) {
                $db->insert('order_changelog', [
                    'order_id' => 99009401, 'event' => $event, 'message' => 'Timezone fixture',
                    'user_id' => 990001, 'created_at' => $date,
                    'snapshot' => json_encode(['context' => $context]),
                ]);
                $ids[] = (int) $db->lastInsertId();
            }
            $db->insert('order_status_history', [
                'order_id' => 99009401, 'status' => 'new', 'changed_at' => '2026-10-15 14:57:21',
                'comment' => 'Согласована задача на отгрузку №99009901',
                'imported_at' => '2026-10-15 14:57:21',
            ]);
            $before = $db->fetchAllAssociative('SELECT * FROM order_changelog WHERE order_id = 99009401 ORDER BY id');
            $tester = new \Symfony\Component\Console\Tester\CommandTester(
                self::getContainer()->get(\App\Application\Command\RepairShippingHistoryTimezoneCommand::class),
            );
            self::assertSame(0, $tester->execute([]));
            self::assertSame($before, $db->fetchAllAssociative('SELECT * FROM order_changelog WHERE order_id = 99009401 ORDER BY id'));
            self::assertSame(0, $tester->execute(['--apply' => true]));
            self::assertSame([
                '2026-10-15 14:56:45', '2026-01-15 13:57:01',
                '2026-10-15 16:26:49', '2026-10-15 11:00:00',
            ], $db->fetchFirstColumn('SELECT created_at FROM order_changelog WHERE order_id = 99009401 ORDER BY id'));
            $snapshot = json_decode($db->fetchOne('SELECT snapshot FROM order_changelog WHERE id = :id', ['id' => $ids[0]]), true);
            self::assertSame('2026-10-15 11:56:45', $snapshot['context']['originalUtcCreatedAt']);
            self::assertSame('Europe/Kyiv', $snapshot['context']['timezone']);
            $after = $db->fetchAllAssociative('SELECT * FROM order_changelog WHERE order_id = 99009401 ORDER BY id');
            self::assertSame(0, $tester->execute(['--apply' => true]));
            self::assertSame($after, $db->fetchAllAssociative('SELECT * FROM order_changelog WHERE order_id = 99009401 ORDER BY id'));
            self::assertSame('2026-10-15 14:57:21', $db->fetchOne('SELECT changed_at FROM order_status_history WHERE order_id = 99009401'));
            $task = $db->fetchAssociative('SELECT created_at, performance_date FROM warehouse_shipping_tasks WHERE id = 99009901');
            self::assertSame(['created_at' => '2026-10-15 14:56:45', 'performance_date' => '2026-10-20 14:30:00'], $task);
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testOnlyMainAndSalesCanApproveEvenWithIndividualPermission(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $db->insert('warehouse_shipping_tasks', [
                'id' => 99009902, 'order_id' => 99009401, 'type' => 2, 'status' => 0,
                'performance_date' => '2026-10-20 14:30:00', 'payload' => '{"storage":2}',
            ]);
            foreach (['ROLE_PRODUCTION', 'ROLE_SHIFT_SUPERVISOR', 'ROLE_PARTNER', 'ROLE_MAIN', 'ROLE_SALES'] as $role) {
                $db->update('identity_users', ['role' => $role], ['id' => 990001]);
                self::getContainer()->get('doctrine.orm.entity_manager')->clear();
                $this->authenticate($client);
                $canApprove = in_array($role, ['ROLE_MAIN', 'ROLE_SALES'], true);
                $crawler = $client->request('GET', '/orders/99009401/shipping/new');
                self::assertResponseIsSuccessful();
                self::assertCount($canApprove ? 1 : 0, $crawler->filter('input[name="allowed"]'));
                $token = $crawler->filter('form [name="_token"]')->attr('value');
                $client->request('GET', '/shipping?q=99009902');
                self::assertResponseIsSuccessful();
                self::assertSelectorCount($canApprove ? 1 : 0, '[data-shipping-status-target="approvalForm"]');
                $expected = $role === 'ROLE_SALES' ? 1 : 0;
                $client->jsonRequest('POST', '/crm/shipments/99009902/approval', [
                    'allowed' => $expected ? 0 : 1, 'expectedAllowed' => $expected, 'returnToShipping' => true,
                ], ['HTTP_X_CSRF_TOKEN' => $token]);
                self::assertResponseStatusCodeSame($canApprove ? 200 : 403);
                if (!$canApprove) {
                    $client->jsonRequest('POST', '/orders/99009401/shipping', [
                        'version' => 0, 'date' => '2026-10-20', 'allowed' => true,
                        'operationId' => '019a0b6c-1111-7000-8000-000000000098',
                        'lines' => [['productId' => $product, 'quantity' => '1']],
                    ], ['HTTP_X_CSRF_TOKEN' => $token]);
                    self::assertResponseStatusCodeSame(403);
                }
            }
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    private function fixture(Connection $db): int {
        foreach ([220, 232, 305, 306, 307, 308, 309, 310, 311, 1700] as $permission) {
            $db->executeStatement('
                INSERT IGNORE INTO identity_user_permissions (user_id, permission_id)
                SELECT 990001, id FROM identity_permissions WHERE legacy_division_id = :permission
            ', ['permission' => $permission]);
        }
        $product = (int) $db->fetchOne('
            SELECT p.id FROM products p INNER JOIN products g ON g.id = p.parent_id
            WHERE p.enabled = 1 AND g.enabled = 1 ORDER BY p.id LIMIT 1
        ');
        $db->update('products', ['storage_office' => '10', 'storage_workshop' => '20'], ['id' => $product]);
        $db->insert('orders', [
            'id' => 99009401, 'customer_name' => 'Отгрузка тест', 'manager_id' => 990001,
            'status' => 'new', 'markup' => 0, 'shipping_cost' => 0,
            'created_at' => '2026-10-15 10:00:00', 'desired_at' => '2026-10-20 14:30:00',
            'imported_at' => '2026-10-15 10:00:00', 'updated_at' => '2026-10-15 10:00:00',
        ]);
        $db->insert('order_items', [
            'order_id' => 99009401, 'product_id' => $product, 'product_name' => 'Плитка',
            'unit' => 'м²', 'quantity' => '4.000', 'unit_price' => '0', 'position' => 1,
        ]);

        return $product;
    }
}
