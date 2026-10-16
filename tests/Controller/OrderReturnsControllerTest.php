<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderReturnsControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testReturnsPageExists(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $client->request('GET', '/warehouse/returns');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h3', 'Возвраты');
        } finally {
            $db->rollBack();
        }
    }

    public function testDraftAcceptanceIsAtomicIdempotentAndDoesNotChangeOrderMoneyOrShipments(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/warehouse/returns');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->first()->attr('value')];
            $draftPayload = ['operationId' => '019a0b6c-2222-7000-8000-000000000001', 'comment' => 'Возврат тест', 'items' => [['productId' => $product, 'quantity' => '2.500']]];
            $client->jsonRequest('POST', '/warehouse/returns/drafts', $draftPayload, $headers);
            self::assertResponseIsSuccessful();
            $draft = json_decode($client->getResponse()->getContent(), true)['id'];
            $client->jsonRequest('POST', '/warehouse/returns/drafts', $draftPayload, $headers);
            self::assertSame($draft, json_decode($client->getResponse()->getContent(), true)['id']);
            $payload = ['operationId' => '019a0b6c-2222-7000-8000-000000000002', 'orderVersion' => 0, 'draftId' => $draft, 'items' => [['productId' => $product, 'quantity' => '1.250']]];
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(), true)['id'];
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseIsSuccessful();
            self::assertSame($id, json_decode($client->getResponse()->getContent(), true)['id']);
            self::assertSame('21.250', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=?', [$product]));
            self::assertSame('10.000', $db->fetchOne('SELECT storage_office FROM products WHERE id=?', [$product]));
            self::assertSame('100.00', $db->fetchOne('SELECT prepayment FROM orders WHERE id=99009701'));
            self::assertSame('new', $db->fetchOne('SELECT status FROM orders WHERE id=99009701'));
            self::assertSame('10.000', $db->fetchOne('SELECT quantity FROM order_items WHERE order_id=99009701'));
            self::assertSame(1, (int) $db->fetchOne('SELECT edit_version FROM orders WHERE id=99009701'));
            self::assertSame(1, (int) $db->fetchOne('SELECT done FROM order_return_drafts WHERE id=?', [$draft]));
            self::assertSame('12.3456', $db->fetchOne('SELECT price FROM order_return_items WHERE return_id=?', [$id]));
            self::assertSame($db->fetchOne('SELECT accepted_at FROM order_returns WHERE id=?', [$id]), $db->fetchOne("SELECT created_at FROM warehouse_history WHERE operation_key LIKE 'return:990001:%'"));
            self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM warehouse_history WHERE operation_key LIKE 'return:990001:%'"));
            self::assertSame('10.000', $db->fetchOne('SELECT SUM(quantity) FROM order_shipment_items i INNER JOIN order_shipments s ON s.id=i.shipment_id WHERE s.order_id=99009701'));
            $client->request('GET', '/orders/99009701');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.order-returns', 'Не проведён');
            $payload['items'][0]['quantity'] = '1.500';
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(409);
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testLimitsCancellationPricesAndPermissionsAreCheckedOnServer(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/orders/99009701/returns/new');
            self::assertResponseIsSuccessful();
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->attr('value')];
            $payload = ['operationId' => '019a0b6c-2222-7000-8000-000000000003', 'orderVersion' => 0, 'items' => [['productId' => $product, 'quantity' => '10.001']]];
            foreach (['10.001', '-1', 'abc', '0', '1.0001', [], true] as $quantity) {
                $payload['items'][0]['quantity'] = $quantity;
                $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
                self::assertResponseStatusCodeSame(400);
            }
            $payload['items'][0]['quantity'] = '1';
            $db->update('orders', ['status' => 'cancelled'], ['id' => 99009701]);
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(400);
            $db->update('orders', ['status' => 'new'], ['id' => 99009701]);
            $db->insert('order_items', ['order_id' => 99009701, 'product_id' => $product, 'product_name' => 'Другая цена', 'unit' => 'м²', 'quantity' => '1', 'unit_price' => '99', 'position' => 2]);
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('20.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=?', [$product]));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM order_returns WHERE order_id=99009701'));
            $db->executeStatement('DELETE a FROM identity_user_permissions a INNER JOIN identity_permissions p ON p.id=a.permission_id WHERE a.user_id=990001 AND p.legacy_division_id=103');
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(403);
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    private function permissions(Connection $db): void {
        foreach ([102, 103, 114, 115, 220] as $permission) {
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?', [$permission]);
        }
    }

    public function testPendingIsSubtractedButProcessedReturnsAreNotSubtractedTwice(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $product = $this->fixture($db);
            // Ten shipped, three processed (already represented by a negative shipment), two pending.
            $db->insert('order_shipments', ['id' => 99009702, 'order_id' => 99009701, 'imported_at' => '2026-10-16 10:00:00']);
            $db->insert('order_shipment_items', ['shipment_id' => 99009702, 'product_id' => $product, 'quantity' => '-3']);
            foreach ([['3', 990001], ['2', null]] as [$quantity, $processor]) {
                $db->insert('order_returns', ['order_id' => 99009701, 'accepted_at' => '2026-10-16 10:00:00', 'accepted_by' => 990001, 'processed_by' => $processor]);
                $db->insert('order_return_items', ['return_id' => $db->lastInsertId(), 'product_id' => $product, 'quantity' => $quantity, 'price' => '12.3456']);
            }
            $crawler = $client->request('GET', '/orders/99009701/returns/new');
            self::assertSame('5.000', $crawler->filter('input[type="number"]')->attr('max'));
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->attr('value')];
            $payload = ['operationId' => '019a0b6c-2222-7000-8000-000000000004', 'orderVersion' => 0, 'items' => [['productId' => $product, 'quantity' => '5.001']]];
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(400);
            $payload['items'][0]['quantity'] = '5.000';
            $db->update('products', ['enabled' => 0], ['id' => $product]);
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseIsSuccessful();
            $payload['operationId'] = '019a0b6c-2222-7000-8000-000000000005';
            $payload['items'][0]['quantity'] = '0.001';
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(409);
            $payload['orderVersion'] = 1;
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('25.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=?', [$product]));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testFailureOnSecondProductRollsBackDraftStockAndHistory(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $product = $this->fixture($db);
            $second = (int) $db->fetchOne('SELECT id FROM products WHERE parent_id IS NOT NULL AND id>? ORDER BY id LIMIT 1', [$product]);
            $db->update('products', ['storage_workshop' => '999999999.999'], ['id' => $second]);
            $db->insert('order_items', ['order_id' => 99009701, 'product_id' => $second, 'product_name' => 'Вторая позиция', 'unit' => 'м²', 'quantity' => '10', 'unit_price' => '1', 'position' => 2]);
            $db->insert('order_shipment_items', ['shipment_id' => 99009701, 'product_id' => $second, 'quantity' => '10']);
            $db->insert('order_return_drafts', ['created_at' => '2026-10-16 10:00:00', 'user_id' => 990001, 'comment' => 'Проверка отката']);
            $draft = (int) $db->lastInsertId();
            $crawler = $client->request('GET', '/orders/99009701/returns/new');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->attr('value')];
            $payload = ['operationId' => '019a0b6c-2222-7000-8000-000000000006', 'orderVersion' => 0, 'draftId' => $draft, 'items' => [['productId' => $product, 'quantity' => '1'], ['productId' => $second, 'quantity' => '1']]];
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('20.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=?', [$product]));
            self::assertSame(0, (int) $db->fetchOne('SELECT done FROM order_return_drafts WHERE id=?', [$draft]));
            self::assertSame(0, (int) $db->fetchOne('SELECT edit_version FROM orders WHERE id=99009701'));
            self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM warehouse_history WHERE operation_key LIKE 'return:990001:%'"));
            self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM order_changelog WHERE order_id=99009701 AND event='return_accepted'"));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    private function fixture(Connection $db): int {
        $db->update('order_return_imports', ['completed_at' => '2026-10-16 10:00:00'], ['id' => 'legacy-v1']);
        $product = (int) $db->fetchOne('SELECT id FROM products WHERE parent_id IS NOT NULL ORDER BY id LIMIT 1');
        $db->update('products', ['storage_office' => '10', 'storage_workshop' => '20'], ['id' => $product]);
        $db->insert('orders', ['id' => 99009701, 'customer_name' => 'Возврат тест', 'status' => 'new', 'prepayment' => '100', 'markup' => 0, 'shipping_cost' => 0, 'created_at' => '2026-10-16 10:00:00', 'desired_at' => '2026-10-20 10:00:00', 'imported_at' => '2026-10-16 10:00:00', 'updated_at' => '2026-10-16 10:00:00']);
        $db->insert('order_items', ['order_id' => 99009701, 'product_id' => $product, 'product_name' => 'Плитка возврат', 'unit' => 'м²', 'quantity' => '10', 'unit_price' => '12.3456', 'position' => 1]);
        $db->insert('order_shipments', ['id' => 99009701, 'order_id' => 99009701, 'imported_at' => '2026-10-16 10:00:00']);
        $db->insert('order_shipment_items', ['shipment_id' => 99009701, 'product_id' => $product, 'quantity' => '10']);

        return $product;
    }

    public function testSamePriceDuplicatesAreAggregatedAndCompletedDraftCannotBeAcceptedAgain(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $product = $this->fixture($db);
            $db->insert('order_items', ['order_id' => 99009701, 'product_id' => $product, 'product_name' => 'Та же цена', 'unit' => 'м²', 'quantity' => '1', 'unit_price' => '12.3456', 'position' => 2]);
            $db->insert('order_return_drafts', ['created_at' => '2026-10-16 10:00:00', 'user_id' => 990001, 'comment' => 'Заявка']);
            $draft = (int) $db->lastInsertId();
            $crawler = $client->request('GET', '/orders/99009701/returns/new?draftId=' . $draft);
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->attr('value')];
            $payload = ['operationId' => '019a0b6c-2222-7000-8000-000000000007', 'orderVersion' => 0, 'draftId' => $draft, 'items' => [['productId' => $product, 'quantity' => '1.25'], ['productId' => $product, 'quantity' => '0.25']]];
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(), true)['id'];
            self::assertSame('1.500', $db->fetchOne('SELECT quantity FROM order_return_items WHERE return_id=?', [$id]));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM order_return_items WHERE return_id=?', [$id]));
            $payload['operationId'] = '019a0b6c-2222-7000-8000-000000000008';
            $payload['orderVersion'] = 1;
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(409);
            self::assertSame('21.500', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=?', [$product]));
            $client->request('GET', '/warehouse/returns/archive');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', 'Заявка');
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testRolesAndSeparateDraftRightsCannotBeBypassedByPostingDirectly(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/orders/99009701/returns/new');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->attr('value')];
            $payload = ['operationId' => '019a0b6c-2222-7000-8000-000000000009', 'orderVersion' => 0, 'items' => [['productId' => $product, 'quantity' => '1']]];
            foreach (['ROLE_SHIFT_SUPERVISOR', 'ROLE_PARTNER'] as $role) {
                $db->update('identity_users', ['role' => $role], ['id' => 990001]);
                $this->authenticate($client);
                $crawler = $client->request('GET', '/warehouse/returns');
                self::assertResponseIsSuccessful();
                $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->first()->attr('value')];
                $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
                self::assertResponseStatusCodeSame(403);
            }
            $db->update('identity_users', ['role' => 'ROLE_MAIN'], ['id' => 990001]);
            $this->authenticate($client);
            $crawler = $client->request('GET', '/warehouse/returns');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->first()->attr('value')];
            $db->executeStatement('DELETE a FROM identity_user_permissions a INNER JOIN identity_permissions p ON p.id=a.permission_id WHERE a.user_id=990001 AND p.legacy_division_id=115');
            $client->jsonRequest('POST', '/warehouse/returns/drafts', $payload, $headers);
            self::assertResponseStatusCodeSame(403);
            $db->executeStatement('DELETE a FROM identity_user_permissions a INNER JOIN identity_permissions p ON p.id=a.permission_id WHERE a.user_id=990001 AND p.legacy_division_id=102');
            $client->request('GET', '/orders/99009701/returns/new');
            self::assertResponseStatusCodeSame(403);
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM order_returns WHERE order_id=99009701'));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testForeignProductAndChangedDraftOperationAreRejected(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->permissions($db);
            $product = $this->fixture($db);
            $crawler = $client->request('GET', '/warehouse/returns');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('[name="_token"]')->attr('value')];
            $payload = ['operationId' => '019a0b6c-2222-7000-8000-000000000010', 'items' => [['productId' => $product, 'quantity' => '1']]];
            $client->jsonRequest('POST', '/warehouse/returns/drafts', $payload, $headers);
            self::assertResponseIsSuccessful();
            $payload['comment'] = 'Другой запрос';
            $client->jsonRequest('POST', '/warehouse/returns/drafts', $payload, $headers);
            self::assertResponseStatusCodeSame(409);
            $payload['items'][0]['productId'] = 999999999;
            $client->jsonRequest('POST', '/orders/99009701/returns', $payload, $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('20.000', $db->fetchOne('SELECT storage_workshop FROM products WHERE id=?', [$product]));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }
}
