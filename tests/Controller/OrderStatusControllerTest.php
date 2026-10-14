<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderStatusControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testStatusChangeChecksPermissionCsrfVersionAndPreservesHistory(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->fixture($db, 99009301);
            $this->grant($db, 220);
            $this->grant($db, 228);
            $crawler = $client->request('GET', '/orders/99009301');
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('.order-status-form'));
            $token = $crawler->filter('[data-order-payment-csrf-value]')->first()->attr('data-order-payment-csrf-value');
            $headers = ['HTTP_X_CSRF_TOKEN' => $token];
            $data = ['status' => 'in_progress', 'version' => 0];
            $client->jsonRequest('POST', '/orders/99009301/status', $data, $headers);
            self::assertResponseStatusCodeSame(403);
            $this->grant($db, 229);
            $crawler = $client->request('GET', '/orders/99009301');
            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('.order-status-form'));
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('.order-status-form [name="_token"]')->attr('value')];
            $client->jsonRequest('POST', '/orders/99009301/status', $data);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', '/orders/99009301/status', $data, $headers);
            self::assertResponseIsSuccessful();
            self::assertSame('in_progress', $db->fetchOne('SELECT status FROM orders WHERE id=99009301'));
            self::assertSame(1, (int) $db->fetchOne('SELECT edit_version FROM orders WHERE id=99009301'));
            $client->jsonRequest('POST', '/orders/99009301/status', $data, $headers);
            self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST', '/orders/99009301/status', $data + ['comment' => 'ignored'], $headers);
            self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST', '/orders/99009301/status', array_replace($data, ['version' => 1]), $headers);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM order_changelog WHERE order_id=99009301 AND event='status_changed'"));
            foreach (['not-a-status', ''] as $invalid) {
                $client->jsonRequest('POST', '/orders/99009301/status', ['status' => $invalid, 'version' => 1], $headers);
                self::assertResponseStatusCodeSame(400);
            }
            $client->jsonRequest('POST', '/orders/999999999/status', $data, $headers);
            self::assertResponseStatusCodeSame(404);
            $client->request('GET', '/orders/99009301');
            self::assertSelectorTextContains('.legacy-order-history', 'В работе');
            self::assertSelectorNotExists('.legacy-order-history details');
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testCancellationCreatesACancelledCrmCopyWithReasonAndNoDuplicateOnRetry(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db, 99009302, markup: 20);
            foreach ([220, 229, 1700] as $permission) $this->grant($db, $permission);
            $crawler = $client->request('GET', '/orders/99009302');
            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('.order-status-form'));
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('.order-status-form [name="_token"]')->attr('value')];
            $client->jsonRequest('POST', '/orders/99009302/status', ['status' => 'cancelled', 'version' => 0, 'comment' => '  '], $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('new', $db->fetchOne('SELECT status FROM orders WHERE id=99009302'));
            $payload = ['status' => 'cancelled', 'version' => 0, 'comment' => 'Клиент передумал'];
            $client->jsonRequest('POST', '/orders/99009302/status', $payload, $headers);
            self::assertResponseIsSuccessful();
            $deal = $db->fetchAssociative('SELECT * FROM crm_deals WHERE source_order_id=99009302');
            self::assertNotFalse($deal);
            self::assertSame('cancelled', $deal['state']);
            self::assertSame('240.00', $deal['budget']);
            self::assertSame('Старый комментарий', $deal['comment']);
            self::assertSame(20, (int) $deal['markup']);
            self::assertSame(990001, (int) $deal['author_id']);
            self::assertSame('2026-10-01 09:00:00', $deal['created_at']);
            self::assertSame('/crm/deals/' . $deal['id'], json_decode($client->getResponse()->getContent(), true)['location']);
            $item = $db->fetchAssociative('SELECT * FROM crm_deal_items WHERE deal_id=?', [$deal['id']]);
            self::assertSame($product, (int) $item['product_id']);
            self::assertSame('2.000', $item['quantity']);
            self::assertSame('100.0000', $item['price']);
            $contact = $db->fetchAssociative('SELECT * FROM crm_deal_contacts WHERE deal_id=?', [$deal['id']]);
            self::assertNull($contact['executor_id']);
            self::assertSame('Клиент передумал', $contact['comment']);
            self::assertSame('cancelled', $db->fetchOne('SELECT status FROM orders WHERE id=99009302'));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM order_items WHERE order_id=99009302'));
            self::assertGreaterThanOrEqual(2, (int) $db->fetchOne('SELECT COUNT(*) FROM crm_deal_history WHERE deal_id=?', [$deal['id']]));
            $client->jsonRequest('POST', '/orders/99009302/status', $payload, $headers);
            self::assertResponseStatusCodeSame(409);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM crm_deals WHERE source_order_id=99009302'));
            $client->request('GET', '/orders/99009302');
            self::assertSelectorNotExists('form[action="/orders/99009302/payments"]');
            self::assertSame(1, (int) $db->fetchOne('SELECT edit_version FROM orders WHERE id=99009302'));
            $client->request('GET', '/crm/deals/' . $deal['id']);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('/orders/99009302', $client->getResponse()->getContent());
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testCancellationIsBlockedByGoodsPaymentsDeliveryPaymentsAndShipments(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->grant($db, 220);
            $this->grant($db, 229);
            foreach (['prepayment', 'delivery', 'shipment'] as $index => $kind) {
                $id = 99009310 + $index;
                $product = $this->fixture($db, $id);
                if ($kind === 'prepayment') {
                    $db->update('orders', ['prepayment' => '1.00'], ['id' => $id]);
                } elseif ($kind === 'delivery') {
                    $db->insert('order_payments', [
                        'order_id' => $id, 'source' => 'shipment', 'source_id' => $id,
                        'amount' => '1.00', 'paid_at' => '2026-10-01 10:00:00',
                        'imported_at' => '2026-10-01 10:00:00',
                    ]);
                } else {
                    $db->insert('order_shipments', [
                        'id' => $id, 'order_id' => $id, 'imported_at' => '2026-10-01 10:00:00',
                    ]);
                    $db->insert('order_shipment_items', [
                        'shipment_id' => $id, 'product_id' => $product, 'quantity' => '1.000',
                    ]);
                }
                $crawler = $client->request('GET', '/orders/' . $id);
                self::assertResponseIsSuccessful();
                self::assertCount(0, $crawler->filter('.order-status-form [value="cancelled"]'));
                $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('.order-status-form [name="_token"]')->attr('value')];
                $client->jsonRequest('POST', '/orders/' . $id . '/status', [
                    'status' => 'cancelled', 'version' => 0, 'comment' => 'Отмена',
                ], $headers);
                self::assertResponseStatusCodeSame(400);
                self::assertSame('new', $db->fetchOne('SELECT status FROM orders WHERE id=:id', ['id' => $id]));
                self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM crm_deals WHERE source_order_id=:id', ['id' => $id]));
            }
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testFinalStatusRequiresMainRoleAndInvalidVersionCannotChangeOrder(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $this->fixture($db, 99009320);
            $this->grant($db, 220);
            $this->grant($db, 229);
            $db->update('identity_users', ['role' => 'ROLE_SALES'], ['id' => 990001]);
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
            $this->authenticate($client);
            $crawler = $client->request('GET', '/orders/99009320');
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('.order-status-form [value="paid_and_delivered"]'));
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('.order-status-form [name="_token"]')->attr('value')];
            $client->jsonRequest('POST', '/orders/99009320/status', [
                'status' => 'paid_and_delivered', 'version' => 0,
            ], $headers);
            self::assertResponseStatusCodeSame(403);
            foreach ([-1, 'bad', '0.5', null] as $version) {
                $client->jsonRequest('POST', '/orders/99009320/status', [
                    'status' => 'in_progress', 'version' => $version,
                ], $headers);
                self::assertResponseStatusCodeSame(400);
            }
            self::assertSame('new', $db->fetchOne('SELECT status FROM orders WHERE id=99009320'));
            $db->update('identity_users', ['role' => 'ROLE_MAIN'], ['id' => 990001]);
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
            $this->authenticate($client);
            $crawler = $client->request('GET', '/orders/99009320');
            self::assertResponseIsSuccessful();
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('.order-status-form [name="_token"]')->attr('value')];
            self::assertCount(1, $crawler->filter('.order-status-form [value="paid_and_delivered"]'));
            $client->jsonRequest('POST', '/orders/99009320/status', [
                'status' => 'paid_and_delivered', 'version' => 0,
            ], $headers);
            self::assertResponseIsSuccessful();
            self::assertSame('paid_and_delivered', $db->fetchOne('SELECT status FROM orders WHERE id=99009320'));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testCancellationRollsBackMissingProductAndAggregatesDuplicatesWithoutCrmAccess(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db, 99009330, markup: 10);
            $this->grant($db, 220);
            $this->grant($db, 229);
            $db->update('order_items', ['product_id' => null], ['order_id' => 99009330]);
            $crawler = $client->request('GET', '/orders/99009330');
            $headers = ['HTTP_X_CSRF_TOKEN' => $crawler->filter('.order-status-form [name="_token"]')->attr('value')];
            $payload = ['status' => 'cancelled', 'version' => 0, 'comment' => 'Причина'];
            $client->jsonRequest('POST', '/orders/99009330/status', $payload, $headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('new', $db->fetchOne('SELECT status FROM orders WHERE id=99009330'));
            self::assertSame(0, (int) $db->fetchOne('SELECT edit_version FROM orders WHERE id=99009330'));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id=99009330'));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM crm_deals WHERE source_order_id=99009330'));
            $db->update('order_items', ['product_id' => $product], ['order_id' => 99009330]);
            $db->insert('order_items', [
                'order_id' => 99009330, 'product_id' => $product, 'product_name' => 'Дубль',
                'unit' => 'м²', 'quantity' => '1.000', 'unit_price' => '160.0000', 'position' => 2,
            ]);
            $client->jsonRequest('POST', '/orders/99009330/status', $payload, $headers);
            self::assertResponseIsSuccessful();
            self::assertSame('/orders/99009330', json_decode($client->getResponse()->getContent(), true)['location']);
            $firstId = (int) $db->fetchOne('SELECT id FROM crm_deals WHERE source_order_id=99009330');
            self::assertSame('396.00', $db->fetchOne('SELECT budget FROM crm_deals WHERE id=:id', ['id' => $firstId]));
            $item = $db->fetchAssociative('SELECT quantity, price FROM crm_deal_items WHERE deal_id=:id', ['id' => $firstId]);
            self::assertSame(['quantity' => '3.000', 'price' => '120.0000'], $item);
            $client->jsonRequest('POST', '/orders/99009330/status', array_replace($payload, ['version' => 1]), $headers);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM crm_deals WHERE source_order_id=99009330'));
            $client->jsonRequest('POST', '/orders/99009330/status', ['status' => 'new', 'version' => 1], $headers);
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST', '/orders/99009330/status', array_replace($payload, ['version' => 2]), $headers);
            self::assertResponseIsSuccessful();
            self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM crm_deals WHERE source_order_id=99009330'));
            self::assertSame('cancelled', $db->fetchOne('SELECT state FROM crm_deals WHERE id=:id', ['id' => $firstId]));
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    private function grant($db, int $permission): void {
        $db->executeStatement(
            'INSERT IGNORE INTO identity_user_permissions(user_id, permission_id) '
                . 'SELECT 990001, id FROM identity_permissions WHERE legacy_division_id=:permission',
            ['permission' => $permission],
        );
    }

    private function fixture($db, int $id, int $markup = 0): int {
        $product = (int) $db->fetchOne('SELECT p.id FROM products p JOIN products g ON g.id=p.parent_id WHERE p.enabled=1 AND g.enabled=1 ORDER BY p.id LIMIT 1');
        $customer = (int) $db->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
        $db->insert('orders', [
            'id' => $id, 'customer_id' => $customer, 'customer_name' => 'Status fixture',
            'manager_id' => 990001, 'comments' => 'Старый комментарий', 'status' => 'new',
            'markup' => $markup, 'shipping_cost' => '50.00', 'shipping_address' => 'Адрес',
            'created_at' => '2026-10-01 09:00:00', 'desired_at' => '2026-10-20 00:00:00',
            'imported_at' => '2026-10-01 09:00:00', 'updated_at' => '2026-10-01 09:00:00',
        ]);
        $db->insert('order_items', [
            'order_id' => $id, 'product_id' => $product, 'product_name' => 'Плитка',
            'unit' => 'м²', 'quantity' => '2.000', 'unit_price' => '100.0000', 'position' => 1,
        ]);
        $db->insert('order_status_history', [
            'order_id' => $id, 'status' => 'new', 'changed_at' => '2026-10-01 09:00:00',
            'comment' => 'Заказ оформлен', 'user_id' => 990001, 'imported_at' => '2026-10-01 09:00:00',
        ]);

        return $product;
    }
}
