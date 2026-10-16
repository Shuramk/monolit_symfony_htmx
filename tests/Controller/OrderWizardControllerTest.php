<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderWizardControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testNewCustomerSearchAndPickupCompleteAllThreeSteps(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $client->request('GET', '/orders/new/delivery');
            self::assertResponseRedirects('/orders/new');
            $token = $this->draft($client, $product, 20);
            $crawler = $client->request('GET', '/orders/new/customer');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.order-wizard-summary', '627.00 грн');
            self::assertSelectorTextContains('.order-wizard-summary', '752.40 грн');
            self::assertSelectorTextContains('.order-wizard-weight', '0.180 т');
            self::assertSelectorNotExists('select[name="customerId"]');
            $key = $crawler->filter('input[name="operationId"]')->attr('value');
            $client->request('GET', '/orders/new/customers/search?q=0671234567');
            self::assertResponseIsSuccessful();
            $customers = json_decode($client->getResponse()->getContent(), true)['customers'];
            self::assertContains(99011001, array_column($customers, 'id'));
            $payload = ['name' => 'Новый клиент мастера', 'phone' => '+38 (067) 765-43-21', 'operationId' => $key];
            $client->jsonRequest('POST', '/orders/new/customers', $payload);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', '/orders/new/customers', $payload, ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(201);
            $id = json_decode($client->getResponse()->getContent(), true)['customer']['id'];
            $client->jsonRequest('POST', '/orders/new/customers', $payload, ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(201);
            self::assertSame($id, json_decode($client->getResponse()->getContent(), true)['customer']['id']);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM customers WHERE name = :name', ['name' => $payload['name']]));
            $client->jsonRequest('POST', '/orders/new/customers', array_replace($payload, ['name' => 'Другие данные']), ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST', '/orders/draft/customer', ['customerId' => $id], ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseIsSuccessful();
            self::assertSame('/orders/new/delivery', json_decode($client->getResponse()->getContent(), true)['location']);
            $crawler = $client->request('GET', '/orders/new/delivery');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.order-wizard-customer-details', $payload['name']);
            self::assertSelectorExists('input[type="checkbox"][name="pickup"]:checked');
            self::assertSelectorExists('fieldset[disabled][hidden]');
            $values = $crawler->selectButton('Сохранить')->form()->getPhpValues();
            $values['pickup'] = '1';
            $values['shippingCost'] = '999.00';
            $values['carrierId'] = '99999999';
            $values['shippingUnitPrice'] = '123.45';
            $client->jsonRequest('POST', '/orders', $values, ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(201);
            $orderId = json_decode($client->getResponse()->getContent(), true)['id'];
            $order = $db->fetchAssociative('SELECT customer_id, shipping_cost, carrier_id, shipping_trips, markup FROM orders WHERE id = :id', ['id' => $orderId]);
            self::assertSame($id, (int) $order['customer_id']);
            self::assertSame('0.00', $order['shipping_cost']);
            self::assertNull($order['carrier_id']);
            self::assertSame(0, (int) $order['shipping_trips']);
            self::assertSame(20, (int) $order['markup']);
            $client->request('GET', '/orders/new/customer');
            self::assertResponseRedirects('/orders/new');
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testCompanyDeliveryIsCalculatedOnServerAndInvalidInputKeepsTheDraft(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $token = $this->draft($client, $product, 10);
            $client->request('GET', '/orders/new/delivery');
            self::assertResponseRedirects('/orders/new/customer');
            $client->jsonRequest('POST', '/orders/draft/customer', ['customerId' => 99011001], ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseIsSuccessful();
            $client->request('GET', '/orders/new');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[name="lines[0][quantity]"][value="2.000"]');
            self::assertSelectorExists('input[name="lines[0][discount]"][value="5.00"]');
            $crawler = $client->request('GET', '/orders/new/delivery');
            self::assertResponseIsSuccessful();
            $values = $crawler->selectButton('Сохранить')->form()->getPhpValues();
            $values = array_replace($values, [
                'pickup' => '0', 'carrierId' => '99011001', 'shippingTrips' => '3',
                'shippingUnitPrice' => '123.45', 'shippingCost' => '0.01',
            ]);
            foreach ([['carrierId' => 999999999], ['shippingTrips' => 0], ['shippingUnitPrice' => '-1'], ['customerId' => 999999999]] as $invalid) {
                $client->jsonRequest('POST', '/orders', array_replace($values, $invalid), ['HTTP_X_CSRF_TOKEN' => $token]);
                self::assertResponseStatusCodeSame(400);
            }
            $client->request('GET', '/orders/new/delivery');
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST', '/orders', $values, ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(201);
            $id = json_decode($client->getResponse()->getContent(), true)['id'];
            $order = $db->fetchAssociative('SELECT carrier_id, shipping_trips, shipping_unit_price, shipping_cost FROM orders WHERE id = :id', ['id' => $id]);
            self::assertSame(99011001, (int) $order['carrier_id']);
            self::assertSame(3, (int) $order['shipping_trips']);
            self::assertSame('123.45', $order['shipping_unit_price']);
            self::assertSame('370.35', $order['shipping_cost']);
            $snapshot = json_decode($db->fetchOne('SELECT snapshot FROM order_changelog WHERE order_id = :id', ['id' => $id]), true);
            self::assertSame('370.35', $snapshot['delivery']['cost']);
            self::assertSame('1097.09', $snapshot['total']);
            $client->request('GET', '/orders/new/delivery');
            self::assertResponseRedirects('/orders/new');
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testCustomerLookupValidationAndPermissions(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = $this->fixture($db);
            $token = $this->draft($client, $product, 0);
            $client->request('GET', '/orders/new/customers/search?q=' . rawurlencode('+38 (067) 123-45-67'));
            self::assertResponseIsSuccessful();
            self::assertContains(99011001, array_column(json_decode($client->getResponse()->getContent(), true)['customers'], 'id'));
            $client->request('GET', '/orders/new/customers/search?q=A');
            self::assertResponseIsSuccessful();
            self::assertSame([], json_decode($client->getResponse()->getContent(), true)['customers']);
            $before = (int) $db->fetchOne('SELECT COUNT(*) FROM customers');
            foreach ([['name' => ' ', 'phone' => '0671234567'], ['name' => 'Клиент', 'phone' => '------']] as $invalid) {
                $client->jsonRequest('POST', '/orders/new/customers', $invalid + ['operationId' => str_repeat('a', 32)], ['HTTP_X_CSRF_TOKEN' => $token]);
                self::assertResponseStatusCodeSame(400);
            }
            self::assertSame($before, (int) $db->fetchOne('SELECT COUNT(*) FROM customers'));
            $db->executeStatement('
                DELETE access FROM identity_user_permissions access
                INNER JOIN identity_permissions permission ON permission.id = access.permission_id
                WHERE access.user_id = 990001 AND permission.legacy_division_id = 220
            ');
            $client->request('GET', '/orders/new/customers/search?q=Wizard');
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', '/orders/new/customers', [
                'name' => 'Запрещено', 'phone' => '0671234567', 'operationId' => str_repeat('b', 32),
            ], ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(403);
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    private function fixture(Connection $db): int {
        $db->executeStatement('
            INSERT IGNORE INTO identity_user_permissions (user_id, permission_id)
            SELECT 990001, id FROM identity_permissions WHERE legacy_division_id = 220
        ');
        $db->insert('customers', [
            'id' => 99011001, 'name' => 'Wizard клиент', 'phone' => '(067) 123-45-67',
            'registered_at' => '2026-10-15 12:00:00', 'imported_at' => '2026-10-15 12:00:00', 'updated_at' => '2026-10-15 12:00:00',
        ]);
        $db->insert('shipping_carriers', ['id' => 99011001, 'name' => 'Перевозчик мастера', 'payload' => '{}']);
        $product = $db->fetchAssociative('
            SELECT product.id, product.parent_id FROM products product
            INNER JOIN products parent ON parent.id = product.parent_id
            WHERE product.enabled = 1 AND parent.enabled = 1 ORDER BY product.id LIMIT 1
        ');
        $db->update('products', ['weight_per_unit' => '90.000'], ['id' => $product['parent_id']]);

        return (int) $product['id'];
    }

    private function draft(KernelBrowser $client, int $product, int $markup): string {
        $crawler = $client->request('GET', '/orders/new');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $client->jsonRequest('POST', '/orders/draft', [
            'markup' => $markup,
            'lines' => [['productId' => $product, 'quantity' => '2.000', 'unitPrice' => '330.0000', 'discount' => '5.00']],
        ], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();

        return $token;
    }
}
