<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Domain\Order\UseCases\CreateOrder;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use App\Tests\Support\IdentityTestUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderEditControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testEditLoadsExistingValuesAndUpdatesWithBeforeAfterHistory(): void {
        [$client, $connection, $id] = $this->prepare();
        try {
            $connection->insert('order_payments', ['source' => 'order', 'source_id' => -$id, 'order_id' => $id, 'paid_at' => '2026-10-07 12:00:00', 'amount' => '10', 'imported_at' => '2026-10-07 12:00:00']);
            $connection->insert('order_shipments', ['id' => -$id, 'order_id' => $id, 'imported_at' => '2026-10-07 12:00:00']);
            $crawler = $client->request('GET', '/orders/'.$id.'/edit');
            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('h3.legacy-order-breadcrumb'));
            self::assertSame('/orders', $crawler->filter('h3.legacy-order-breadcrumb a')->eq(0)->attr('href'));
            self::assertSame('/orders/'.$id, $crawler->filter('h3.legacy-order-breadcrumb a')->eq(1)->attr('href'));
            self::assertCount(1, $crawler->filter('.legacy-order-form-customer-details'));
            self::assertCount(1, $crawler->filter('.legacy-order-form-fields input[name="desiredAt"]'));
            self::assertCount(1, $crawler->filter('.legacy-order-form-fields input[name="shippingCost"]'));
            self::assertCount(1, $crawler->filter('.legacy-order-form-actions button[type="submit"]'));
            self::assertSame('/orders/'.$id, $crawler->filter('.legacy-order-form-actions a')->attr('href'));
            self::assertCount(1, $crawler->filter('.legacy-order-form .error[data-json-form-target="error"][role="alert"][hidden]'));
            self::assertSame('/orders/'.$id, $crawler->filter('form.legacy-order-form')->attr('action'));
            self::assertSame('2.000', $crawler->filter('input[name="lines[0][quantity]"]')->attr('value'));
            self::assertSame('100.0000', $crawler->filter('input[name="lines[0][unitPrice]"]')->attr('value'));
            self::assertSame('0', $crawler->filter('input[name="version"]')->attr('value'));
            self::assertSame('Старый адрес', $crawler->filter('textarea[name="shippingAddress"]')->text());
            self::assertSame('order-form json-form', $crawler->filter('form.legacy-order-form')->attr('data-controller'));
            $payload = $this->payload($connection, $id);
            self::assertSame((string) $payload['lines'][0]['productId'], $crawler->filter('select[name="lines[0][productId]"] option[selected]')->attr('value'));
            $payload['lines'][0]['quantity'] = '3.500';
            $payload['lines'][0]['unitPrice'] = '120.0000';
            $payload['shippingAddress'] = 'Новый адрес';
            $payload['comments'] = '<script>alert(1)</script>';
            $this->post($client, $id, $payload, $crawler->filter('input[name="_token"]')->attr('value'));
            self::assertResponseIsSuccessful();
            self::assertSame('/orders/'.$id, json_decode($client->getResponse()->getContent(), true)['location']);
            self::assertSame('3.500', $connection->fetchOne('SELECT quantity FROM order_items WHERE order_id = ?', [$id]));
            self::assertSame('120.0000', $connection->fetchOne('SELECT unit_price FROM order_items WHERE order_id = ?', [$id]));
            self::assertSame($payload['lines'][0]['lineId'], (int) $connection->fetchOne('SELECT id FROM order_items WHERE order_id = ?', [$id]));
            self::assertSame(1, (int) $connection->fetchOne('SELECT edit_version FROM orders WHERE id = ?', [$id]));
            self::assertSame('new', $connection->fetchOne('SELECT status FROM orders WHERE id = ?', [$id]));
            self::assertSame('10.00', $connection->fetchOne('SELECT amount FROM order_payments WHERE order_id = ?', [$id]));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_shipments WHERE order_id = ?', [$id]));
            $event = $connection->fetchAssociative("SELECT message, snapshot FROM order_changelog WHERE order_id = ? AND event = 'updated'", [$id]);
            self::assertSame('Заказ редактировался', $event['message']);
            $snapshot = json_decode($event['snapshot'], true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('200.00', $snapshot['context']['before']['goodsTotal']);
            self::assertSame('420.00', $snapshot['goodsTotal']);
            self::assertSame('Старый адрес', $snapshot['context']['before']['delivery']['address']);
            self::assertSame('Новый адрес', $snapshot['delivery']['address']);
            $client->request('GET', '/orders/'.$id);
            self::assertResponseIsSuccessful();
            self::assertCount(2, $client->getCrawler()->filter('details.order-changelog-entry:not([open])'));
            self::assertCount(1, $client->getCrawler()->filter('details.order-changelog-full:not([open])'));
            self::assertSame('Полный снимок', $client->getCrawler()->filter('details.order-changelog-full > summary')->text());
            self::assertStringContainsString('Изменена цена:', $client->getCrawler()->filter('.order-changelog-changes')->text());
            self::assertStringContainsString('100 → 120 грн', $client->getCrawler()->filter('.order-changelog-changes')->text());
            self::assertStringContainsString('2 → 3.5', $client->getCrawler()->filter('.order-changelog-changes')->text());
            self::assertCount(2, $client->getCrawler()->filter('.order-changelog-full-section > .order-changelog-snapshot'));
            self::assertStringContainsString('До изменения:', $client->getResponse()->getContent());
            self::assertStringContainsString('После изменения:', $client->getResponse()->getContent());
            self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        } finally {
            $connection->delete('orders', ['id' => $id]);
        }
    }

    public function testNoOpDoesNotAddHistoryAndStaleVersionCannotOverwriteChanges(): void {
        [$client, $connection, $id] = $this->prepare();
        try {
            $crawler = $client->request('GET', '/orders/'.$id.'/edit');
            $token = $crawler->filter('input[name="_token"]')->attr('value');
            $payload = $this->payload($connection, $id);
            $this->post($client, $id, $payload, $token);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$id]));
            self::assertSame(0, (int) $connection->fetchOne('SELECT edit_version FROM orders WHERE id = ?', [$id]));
            $payload['comments'] = 'Изменение';
            $this->post($client, $id, $payload, $token);
            self::assertResponseIsSuccessful();
            $payload['comments'] = 'Устаревшая вкладка';
            $this->post($client, $id, $payload, $token);
            self::assertResponseStatusCodeSame(409);
            self::assertSame('Изменение', $connection->fetchOne('SELECT comments FROM orders WHERE id = ?', [$id]));
            self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$id]));
        } finally {
            $connection->delete('orders', ['id' => $id]);
        }
    }

    public function testAddReorderAndRemoveLinesKeepExistingIds(): void {
        [$client, $connection, $id] = $this->prepare();
        try {
            $crawler = $client->request('GET', '/orders/'.$id.'/edit');
            $token = $crawler->filter('input[name="_token"]')->attr('value');
            $payload = $this->payload($connection, $id);
            $oldId = $payload['lines'][0]['lineId'];
            $new = $payload['lines'][0];
            $new['lineId'] = 0;
            $new['quantity'] = '1.500';
            $new['discount'] = '10';
            array_unshift($payload['lines'], $new);
            $this->post($client, $id, $payload, $token);
            self::assertResponseIsSuccessful();
            $lines = $connection->fetchAllAssociative('SELECT id, quantity, unit_price FROM order_items WHERE order_id = ? ORDER BY position', [$id]);
            self::assertCount(2, $lines);
            self::assertSame('1.500', $lines[0]['quantity']);
            self::assertSame('90.0000', $lines[0]['unit_price']);
            self::assertSame($oldId, (int) $lines[1]['id']);
            $payload['version'] = 1;
            $payload['lines'] = [['lineId' => (int) $lines[0]['id'], 'productId' => $new['productId'], 'quantity' => '1.500', 'unitPrice' => '90.0000', 'discount' => '0']];
            $this->post($client, $id, $payload, $token);
            self::assertResponseIsSuccessful();
            self::assertSame([(int) $lines[0]['id']], array_map('intval', $connection->fetchFirstColumn('SELECT id FROM order_items WHERE order_id = ?', [$id])));
            self::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$id]));
        } finally {
            $connection->delete('orders', ['id' => $id]);
        }
    }

    public function testPaidMarkupAndCancelledOrderAreProtectedOnServer(): void {
        [$client, $connection, $id] = $this->prepare();
        try {
            $connection->update('orders', ['prepayment' => '10'], ['id' => $id]);
            $crawler = $client->request('GET', '/orders/'.$id.'/edit');
            self::assertCount(1, $crawler->filter('select[data-order-form-target="markupMode"][disabled]'));
            $token = $crawler->filter('input[name="_token"]')->attr('value');
            $payload = $this->payload($connection, $id);
            $payload['markup'] = 20;
            $this->post($client, $id, $payload, $token);
            self::assertResponseStatusCodeSame(400);
            self::assertSame(0, (int) $connection->fetchOne('SELECT markup FROM orders WHERE id = ?', [$id]));
            $connection->update('orders', ['status' => 'cancelled'], ['id' => $id]);
            $payload['markup'] = 0;
            $this->post($client, $id, $payload, $token);
            self::assertResponseStatusCodeSame(400);
            $client->request('GET', '/orders/'.$id.'/edit');
            self::assertResponseStatusCodeSame(400);
        } finally {
            $connection->delete('orders', ['id' => $id]);
        }
    }

    public function testInvalidLinesForeignLineAndCsrfDoNotChangeOrder(): void {
        [$client, $connection, $id] = $this->prepare();
        try {
            $crawler = $client->request('GET', '/orders/'.$id.'/edit');
            $token = $crawler->filter('input[name="_token"]')->attr('value');
            $original = $this->payload($connection, $id);
            $this->post($client, $id, $original, 'invalid');
            self::assertResponseStatusCodeSame(403);
            foreach (['quantity' => '-1', 'unitPrice' => '-1', 'discount' => '101', 'lineId' => 999999999, 'productId' => 999999999] as $field => $value) {
                $invalid = $original;
                $invalid['lines'][0][$field] = $value;
                $this->post($client, $id, $invalid, $token);
                self::assertResponseStatusCodeSame(400);
            }
            $invalid = $original;
            $invalid['lines'][] = $original['lines'][0];
            $this->post($client, $id, $invalid, $token);
            self::assertResponseStatusCodeSame(400);
            $invalid['lines'] = [];
            $this->post($client, $id, $invalid, $token);
            self::assertResponseStatusCodeSame(400);
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id = ?', [$id]));
            self::assertSame('2.000', $connection->fetchOne('SELECT quantity FROM order_items WHERE order_id = ?', [$id]));
        } finally {
            $connection->delete('orders', ['id' => $id]);
        }
    }

    public function testUnavailableExistingProductKeepsHistoricalNameAndPrice(): void {
        [$client, $connection, $id] = $this->prepare();
        $productId = (int) $connection->fetchOne('SELECT product_id FROM order_items WHERE order_id = ?', [$id]);
        $enabled = $connection->fetchOne('SELECT enabled FROM products WHERE id = ?', [$productId]);
        try {
            $connection->update('order_items', ['product_name' => 'Историческое название'], ['order_id' => $id]);
            $connection->update('products', ['enabled' => 0], ['id' => $productId]);
            $crawler = $client->request('GET', '/orders/'.$id.'/edit');
            self::assertResponseIsSuccessful();
            self::assertSame('Историческое название', $crawler->filter('select[name="lines[0][productId]"] option[selected]')->text());
            $payload = $this->payload($connection, $id);
            $payload['comments'] = 'Сохранение недоступного товара';
            $this->post($client, $id, $payload, $crawler->filter('input[name="_token"]')->attr('value'));
            self::assertResponseIsSuccessful();
            self::assertSame('Историческое название', $connection->fetchOne('SELECT product_name FROM order_items WHERE order_id = ?', [$id]));
            self::assertSame('100.0000', $connection->fetchOne('SELECT unit_price FROM order_items WHERE order_id = ?', [$id]));
        } finally {
            $connection->update('products', ['enabled' => $enabled], ['id' => $productId]);
            $connection->delete('orders', ['id' => $id]);
        }
    }

    public function testUnknownOrderAndUnauthenticatedEdit(): void {
        [$client, $connection, $id] = $this->prepare();
        try {
            $client->request('GET', '/orders/999999999/edit');
            self::assertResponseStatusCodeSame(404);
            $crawler = $client->request('GET', '/orders/'.$id.'/edit');
            $this->post($client, 999999999, $this->payload($connection, $id), $crawler->filter('input[name="_token"]')->attr('value'));
            self::assertResponseStatusCodeSame(404);
            $client->getCookieJar()->clear();
            $client->request('GET', '/orders/'.$id.'/edit');
            self::assertResponseRedirects('/login');
        } finally {
            $connection->delete('orders', ['id' => $id]);
        }
    }

    /** @return array{KernelBrowser, Connection, int} */
    private function prepare(): array {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $permission = $connection->fetchOne('SELECT id FROM identity_permissions WHERE legacy_division_id = 220');
        $connection->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)', [990001, $permission]);
        $customerId = (int) $connection->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
        $productId = (int) $connection->fetchOne('SELECT product.id FROM products product INNER JOIN products parent ON parent.id = product.parent_id WHERE product.enabled = 1 AND parent.enabled = 1 ORDER BY product.id LIMIT 1');
        $now = new \DateTimeImmutable('2026-10-07 00:00:00');
        $dto = new CreateOrderDto($customerId, $now, 'Старый адрес', null, '0', 0, [new CreateOrderLineDto($productId, '2.000', '100', '0')]);
        $id = self::getContainer()->get(CreateOrder::class)->execute($dto, 990001, $now);

        return [$client, $connection, $id];
    }

    /** @return array<string, mixed> */
    private function payload(Connection $connection, int $id): array {
        $line = $connection->fetchAssociative('SELECT id, product_id, quantity, unit_price FROM order_items WHERE order_id = ?', [$id]);

        return [
            'version' => 0, 'desiredAt' => '2026-10-07', 'markup' => 0,
            'shippingAddress' => 'Старый адрес', 'shippingCost' => '0', 'comments' => null,
            'lines' => [['lineId' => (int) $line['id'], 'productId' => (int) $line['product_id'], 'quantity' => $line['quantity'], 'unitPrice' => $line['unit_price'], 'discount' => '0']],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function post(KernelBrowser $client, int $id, array $payload, string $token): void {
        $client->request('POST', '/orders/'.$id, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
