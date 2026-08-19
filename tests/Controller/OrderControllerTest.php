<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testOrderPrintIsProtectedAndRendersOnlyThePrintableDocument(): void {
        $client = static::createClient();
        $client->request('GET', '/orders/26100738/print');
        self::assertResponseRedirects('http://localhost/login');
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $permission = $connection->fetchOne('SELECT id FROM identity_permissions WHERE legacy_division_id = 220');
        $connection->delete('identity_user_permissions', ['user_id' => 990001, 'permission_id' => $permission]);
        $id = $connection->fetchOne('SELECT id FROM orders ORDER BY id DESC LIMIT 1');
        $client->request('GET', '/orders/'.$id.'/print');
        self::assertResponseStatusCodeSame(403);
        $connection->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)', [990001, $permission]);
        $client->request('GET', '/orders/'.$id);
        self::assertSelectorExists('a[href="/orders/'.$id.'/print"][target="_blank"][rel="noopener"]');
        $before = $connection->fetchAssociative('SELECT status, edit_version, updated_at FROM orders WHERE id = ?', [$id]);
        $client->request('GET', '/orders/'.$id.'/print');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'ЗАКАЗ №'.$id);
        self::assertSelectorNotExists('.app-navigation');
        self::assertSelectorNotExists('.order-changelog-entry');
        self::assertCount(6, $client->getCrawler()->filter('.order-print-items thead th'));
        self::assertSelectorExists('button[data-action="order-print#print"]');
        self::assertSelectorExists('input[type="checkbox"][data-action="order-print#togglePaid"]');
        self::assertSelectorExists('[data-order-print-target="stamp"][hidden]');
        self::assertSelectorExists('img.order-print-logo[width="45"][height="45"]');
        self::assertSelectorExists('img[data-order-print-target="stamp"][alt="Оплачено"]');
        self::assertCount(2, $client->getCrawler()->filter('.order-print-note'));
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        self::assertSame($before, self::getContainer()->get('doctrine.dbal.app_connection')->fetchAssociative('SELECT status, edit_version, updated_at FROM orders WHERE id = ?', [$id]));
        $client->request('GET', '/orders/999999999/print');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTableHeadersToggleSortingAndKeepSearchFilters(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $permission = $connection->fetchOne('SELECT id FROM identity_permissions WHERE legacy_division_id = 220');
        $connection->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)', [990001, $permission]);
        $client->request('GET', '/orders', ['sort' => 'customer', 'direction' => 'ASC', 'q' => 'А', 'statuses' => ['new']], [], ['HTTP_HX-Request' => 'true']);
        self::assertResponseIsSuccessful();
        self::assertCount(8, $client->getCrawler()->filter('.legacy-orders-grid thead th'));
        self::assertCount(5, $client->getCrawler()->filter('.legacy-orders-grid thead a'));
        self::assertSelectorExists('th[aria-sort="ascending"] a[data-sort="customer"]');
        foreach ($client->getCrawler()->filter('.legacy-orders-grid thead a') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
            self::assertSame('А', $params['q']);
            self::assertSame(['new'], $params['statuses']);
            self::assertSame('DESC', $params['direction']);
            self::assertSame('1', $params['page']);
        }
        self::assertSelectorExists('#order-list-filters input[name="sort"][value="customer"]');
        self::assertSelectorExists('.legacy-orders-advanced-popup input[name="direction"][value="ASC"]');
        $client->click($client->getCrawler()->filter('a[data-sort="customer"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('th[aria-sort="descending"] a[data-sort="customer"]');
    }

    public function testOrderListRequiresLegacyPermissionAndReturnsHtmxRows(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("DELETE FROM identity_user_permissions WHERE user_id = ? AND permission_id = ?", [990001, $permissionId]);

        $client->request("GET", "/orders");
        self::assertResponseStatusCodeSame(403);

        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $client->request('GET', '/orders');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h3.legacy-order-breadcrumb', 'Заказы » Список заказов');
        self::assertSame('/orders', $client->getCrawler()->filter('h3.legacy-order-breadcrumb a')->attr('href'));
        self::assertCount(2, $client->getCrawler()->filter('nav.pagination[hx-target="#order-list"]'));
        self::assertSelectorExists('nav.pagination [aria-current="page"]');
        $client->request("GET", "/orders?q=20", [], [], ["HTTP_HX-Request" => "true"]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString("<tr", $client->getResponse()->getContent());
        self::assertStringNotContainsString('<html', $client->getResponse()->getContent());
        self::assertSelectorExists('form.legacy-orders-controls[hx-target="#order-list"]');
        self::assertSelectorTextSame('.legacy-order-create-button', 'Создать новый заказ');
        self::assertSelectorExists('input#order-search[name="q"]');
        self::assertCount(7, $client->getCrawler()->filter('.legacy-orders-controls input[type="checkbox"][name="statuses[]"]'));
        self::assertCount(7, $client->getCrawler()->filter('.legacy-orders-advanced-popup input[type="checkbox"][name="statuses[]"]'));
        self::assertSelectorExists('details[data-controller="order-search"] summary');
        self::assertSelectorExists('input[name="search[orderId]"]');
        self::assertSelectorExists('select[name="search[parentId]"]');
        self::assertSelectorExists('select[name="search[productId]"]');
        self::assertSelectorExists('.legacy-orders-pagination [aria-current="page"]');
    }

    public function testAdvancedSearchFindsClosedOrdersAndPreservesFormState(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $permission = $connection->fetchOne('SELECT id FROM identity_permissions WHERE legacy_division_id = 220');
        $connection->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)', [990001, $permission]);
        $id = $connection->fetchOne("SELECT id FROM orders WHERE status = 'paid_and_delivered' ORDER BY id DESC LIMIT 1");
        self::assertNotFalse($id);

        $client->request('GET', '/orders', ['search' => ['orderId' => (string) $id]], [], ['HTTP_HX-Request' => 'true']);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('<html', $client->getResponse()->getContent());
        self::assertSelectorExists('#order-list-rows a[href="/orders/'.$id.'"]');
        self::assertSame((string) $id, $client->getCrawler()->filter('#search-order-id')->attr('value'));
        self::assertSelectorExists('#order-list-filters input[type="hidden"][name="search[orderId]"]');
        self::assertCount(7, $client->getCrawler()->filter('.legacy-orders-controls input[type="checkbox"]:checked[name="statuses[]"]'));
    }

    public function testOrderCardShowsAnImportedOrderAndReturns404ForUnknownNumber(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $appConnection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($appConnection instanceof Connection);
        $permissionId = $appConnection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $appConnection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $orderId = $appConnection->fetchOne("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
        assert(is_numeric($orderId));
        self::assertTrue((bool) $appConnection->fetchOne("SELECT customer_id IS NOT NULL FROM orders WHERE id = ?", [$orderId]));

        $client->request("GET", "/orders/".$orderId);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("Заказ №".$orderId, $client->getResponse()->getContent());
        self::assertSelectorTextSame('h3.legacy-order-breadcrumb', 'Заказы » Заказ №'.$orderId);
        self::assertSame('/orders', $client->getCrawler()->filter('h3.legacy-order-breadcrumb a')->attr('href'));
        $createdAt = new \DateTimeImmutable($appConnection->fetchOne('SELECT created_at FROM orders WHERE id = ?', [$orderId]));
        self::assertStringContainsString('Время создания заказа '.$createdAt->format('d-m-Y H:i:s'), $client->getResponse()->getContent());
        self::assertStringContainsString("История работы с заказом", $client->getResponse()->getContent());
        $markup = (int) $appConnection->fetchOne('SELECT markup FROM orders WHERE id = ?', [$orderId]);
        self::assertCount($markup > 0 ? 8 : 7, $client->getCrawler()->filter('.legacy-order-lines > thead > tr > th'));
        self::assertSelectorTextSame('.legacy-order-lines > thead .legacy-order-weight', 'Вес');
        self::assertSelectorTextSame('.legacy-order-lines > thead .legacy-order-stock', 'Склад*');
        self::assertSelectorExists('.legacy-order-head .legacy-order-actions a[href="/orders/'.$orderId.'/edit"]');
        self::assertSelectorTextSame('.legacy-order-lines tfoot tr:first-child th', 'Итого:');
        self::assertCount(2, $client->getCrawler()->filter('.legacy-order-payment-totals dt'));
        self::assertCount(2, $client->getCrawler()->filter('.legacy-order-delivery-totals dt'));
        self::assertSelectorExists('.legacy-order-columns .legacy-order-status-panel');
        self::assertSelectorExists('.legacy-order-history col.legacy-order-history-date');

        $client->request("GET", "/orders/999999999");
        self::assertResponseStatusCodeSame(404);
    }

    public function testOrderWorkHistoryDoesNotRenderASeparateStatusColumn(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $orderId = $connection->fetchOne("SELECT order_id FROM order_status_history WHERE status = 'paid_and_delivered' ORDER BY id DESC LIMIT 1");
        assert(is_numeric($orderId));

        $client->request("GET", "/orders/".$orderId);

        self::assertStringContainsString("История работы с заказом", $client->getResponse()->getContent());
        self::assertStringNotContainsString("История статусов", $client->getResponse()->getContent());
        self::assertStringNotContainsString("<td>paid_and_delivered</td>", $client->getResponse()->getContent());
    }

    public function testCreateOrderWritesOnlyToApplicationDatabase(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $customerId = $connection->fetchOne("SELECT id FROM customers ORDER BY id LIMIT 1");
        $productId = $connection->fetchOne('
            SELECT product.id FROM products product
            INNER JOIN products parent ON parent.id = product.parent_id
            WHERE product.enabled = 1 AND parent.enabled = 1
            ORDER BY product.id LIMIT 1
        ');
        assert(is_numeric($customerId));
        assert(is_numeric($productId));
        $client->request("GET", "/orders");
        $session = $client->getRequest()->getSession();
        $session->set("_csrf/session", "test-create-order-token");
        $session->save();

        $client->request("POST", "/orders/draft", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => "test-create-order-token"], content: json_encode([
            "markup" => 0,
            "lines" => [["productId" => (int) $productId, "quantity" => "2.000", "unitPrice" => "100.0000", "discount" => "5.00"]],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => "test-create-order-token"], content: json_encode([
            "customerId" => (int) $customerId,
            "desiredAt" => "2026-08-20 00:00:00",
            "shippingAddress" => "Тестовый адрес",
            "shippingCost" => "25.50",
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $orderId = $payload["id"];

        try {
            self::assertSame((int) $customerId, (int) $connection->fetchOne("SELECT customer_id FROM orders WHERE id = ?", [$orderId]));
            self::assertSame(990001, (int) $connection->fetchOne('SELECT manager_id FROM orders WHERE id = ?', [$orderId]));
            self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM order_items WHERE order_id = ?", [$orderId]));
            self::assertNotSame("", $connection->fetchOne("SELECT product_group_name FROM order_items WHERE order_id = ?", [$orderId]));
            self::assertSame("95.0000", $connection->fetchOne("SELECT unit_price FROM order_items WHERE order_id = ?", [$orderId]));
            $entry = $connection->fetchAssociative("SELECT event, message, snapshot, user_id FROM order_changelog WHERE order_id = ?", [$orderId]);
            self::assertIsArray($entry);
            self::assertSame("created", $entry['event']);
            self::assertSame("Заказ оформлен", $entry['message']);
            self::assertSame(990001, (int) $entry['user_id']);
            $snapshot = json_decode($entry['snapshot'], true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('95.0000', $snapshot['lines'][0]['unitPrice']);
            self::assertSame('2.000', $snapshot['lines'][0]['quantity']);
            self::assertSame('5.00', $snapshot['context']['lineInputs'][0]['discount']);
            self::assertSame('190.00', $snapshot['goodsTotal']);
            self::assertSame('215.50', $snapshot['total']);
            self::assertSame('Тестовый адрес', $snapshot['delivery']['address']);
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_status_history WHERE order_id = ?', [$orderId]));

            $client->request('GET', '/orders/'.$orderId);
            self::assertResponseIsSuccessful();
            self::assertCount(1, $client->getCrawler()->filter('.order-changelog-snapshot'));
            self::assertCount(0, $client->getCrawler()->filter('.order-changelog-changes, .order-changelog-full'));
            self::assertCount(1, $client->getCrawler()->filter('details.order-changelog-entry:not([open])'));
            self::assertCount(0, $client->getCrawler()->filter('details.order-changelog-entry[open]'));
            self::assertStringContainsString('Заказ оформлен', $client->getCrawler()->filter('details.order-changelog-entry > summary')->text());
            self::assertCount(1, $client->getCrawler()->filter('details.order-changelog-entry > .order-changelog-snapshot'));
            self::assertStringContainsString('Заказ оформлен', $client->getResponse()->getContent());
            self::assertStringContainsString('Тестовый адрес', $client->getCrawler()->filter('.order-changelog-snapshot')->text());
        } finally {
            $connection->executeStatement("DELETE FROM orders WHERE id = ?", [$orderId]);
        }
    }

    public function testNewOrderFormUsesApplicationCustomersAndProducts(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);

        $client->request("GET", "/orders/new");

        self::assertResponseIsSuccessful();
        self::assertStringContainsString("Создание нового заказа", $client->getResponse()->getContent());
        self::assertCount(1, $client->getCrawler()->filter('.legacy-order-form .error[data-json-form-target="error"][role="alert"][hidden]'));
        self::assertStringNotContainsString("Выберите заказчика", $client->getResponse()->getContent());
        self::assertStringContainsString("Выбрать продукт", $client->getResponse()->getContent());
        self::assertStringContainsString("Выбрать вариант", $client->getResponse()->getContent());
        self::assertStringContainsString("Скидка", $client->getResponse()->getContent());
        self::assertStringContainsString("ФОП", $client->getResponse()->getContent());
        self::assertStringContainsString("НДС", $client->getResponse()->getContent());
        self::assertStringContainsString("Итого с бн", $client->getResponse()->getContent());
        self::assertCount(1, $client->getCrawler()->filter(".legacy-order-create-table-wrap"));
        self::assertCount(1, $client->getCrawler()->filter("table.legacy-order-create-totals"));
        self::assertCount(2, $client->getCrawler()->filter("input[data-order-line-vat-price]"));
        self::assertCount(2, $client->getCrawler()->filter("input[data-order-line-fop-price]"));
        self::assertCount(2, $client->getCrawler()->filter("select[data-order-line-parent][data-action=\x22change->order-form#groupChanged\x22]"));
        self::assertCount(0, $client->getCrawler()->filter("select[data-order-line-parent][hx-get]"));
    }
    public function testNewOrderFormKeepsLineTemplateInsideStimulusController(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);

        $client->request("GET", "/orders/new");
        self::assertCount(1, $client->getCrawler()->filter("form.legacy-order-form template[data-order-form-target=\"template\"]"));
    }

    public function testTwoStepJsonCreatesOrderFromSessionDraftAndClearsIt(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $customerId = (string) $connection->fetchOne("SELECT id FROM customers ORDER BY id LIMIT 1");
        $productId = (string) $connection->fetchOne('
            SELECT product.id FROM products product
            INNER JOIN products parent ON parent.id = product.parent_id
            WHERE product.enabled = 1 AND parent.enabled = 1
            ORDER BY product.id LIMIT 1
        ');
        $crawler = $client->request("GET", "/orders/new");
        $token = $crawler->filter('input[name="_token"]')->attr("value");

        $client->request("POST", "/orders/draft", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode([
            "markup" => "10",
            "lines" => [["productId" => $productId, "quantity" => "2.500", "unitPrice" => "100.0000", "discount" => "5.00"]],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame("content-type", "application/json");
        $draftResponse = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame("/orders/new/customer", $draftResponse["location"]);
        $crawler = $client->request("GET", $draftResponse["location"]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.legacy-order-form .error[data-json-form-target="error"][role="alert"][hidden]'));
        $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode([
            "customerId" => $customerId,
            "desiredAt" => "not-a-date",
            "shippingCost" => "25.50",
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame("content-type", "application/json");
        self::assertArrayHasKey("message", json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
        $crawler = $client->request("GET", "/orders/new/customer");
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton("Сохранить")->form([
            "customerId" => $customerId,
            "desiredAt" => "2026-10-21",
            "shippingCost" => "25.50",
            "shippingAddress" => "Адрес двухшагового заказа",
            "comments" => "Проверка черновика",
        ]);
        $customerPayload = $form->getPhpValues();
        unset($customerPayload["_token"]);
        $customerPayload["markup"] = 20;
        $customerPayload["lines"] = [["productId" => 999999999, "quantity" => "100"]];
        $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode(array_replace($customerPayload, ["customerId" => 999999999]), JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('Клиент не найден.', json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['message']);
        $client->request("GET", "/orders/new/customer");
        self::assertResponseIsSuccessful();

        $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode($customerPayload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $location = $created["location"];
        self::assertMatchesRegularExpression('~/orders/\d+$~', $location);
        $orderId = (int) basename($location);

        try {
            $order = $connection->fetchAssociative("SELECT customer_id, markup, desired_at, shipping_cost, shipping_address FROM orders WHERE id = ?", [$orderId]);
            self::assertSame((int) $customerId, (int) $order["customer_id"]);
            self::assertSame(10, (int) $order["markup"]);
            self::assertSame("2026-10-21 00:00:00", $order["desired_at"]);
            self::assertSame(25.5, (float) $order["shipping_cost"]);
            self::assertSame("Адрес двухшагового заказа", $order["shipping_address"]);
            $line = $connection->fetchAssociative("SELECT product_id, quantity, unit_price FROM order_items WHERE order_id = ?", [$orderId]);
            self::assertSame((int) $productId, (int) $line["product_id"]);
            self::assertSame("2.500", $line["quantity"]);
            self::assertSame("95.0000", $line["unit_price"]);
            $client->request("GET", $location);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString("Заказ №".$orderId, $client->getResponse()->getContent());
            $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode($customerPayload, JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(400);
            $client->request("GET", "/orders/new/customer");
            self::assertResponseRedirects("/orders/new");
        } finally {
            $connection->executeStatement("DELETE FROM orders WHERE id = ?", [$orderId]);
        }
    }

    public function testDraftRejectsEmptyOrInvalidLines(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $crawler = $client->request("GET", "/orders/new");
        $token = $crawler->filter('input[name="_token"]')->attr("value");

        foreach ([
            [],
            [["productId" => "1", "quantity" => "0"]],
            [["productId" => "bad", "quantity" => "1"]],
            [["productId" => "1", "quantity" => "1", "unitPrice" => "-1"]],
            [["productId" => "1", "quantity" => "1", "discount" => "101"]],
        ] as $lines) {
            $client->request("POST", "/orders/draft", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode(["markup" => "0", "lines" => $lines], JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(400);
            self::assertResponseHeaderSame("content-type", "application/json");
        }
        $client->request("GET", "/orders/new/customer");
        self::assertResponseRedirects("/orders/new");
    }

    public function testCreateRequiresCsrfAndDivisionPermission(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $crawler = $client->request("GET", "/orders/new");
        $token = $crawler->filter('input[name="_token"]')->attr("value");
        $customerId = (int) $connection->fetchOne("SELECT id FROM customers ORDER BY id LIMIT 1");
        $productId = (int) $connection->fetchOne("SELECT id FROM products WHERE enabled = 1 AND parent_id IS NOT NULL ORDER BY id LIMIT 1");
        $payload = [
            "customerId" => $customerId,
            "desiredAt" => "2026-10-21",
            "lines" => [["productId" => $productId, "quantity" => "1"]],
        ];
        $count = (int) $connection->fetchOne("SELECT COUNT(*) FROM orders");

        $client->request("POST", "/orders", []);
        self::assertResponseStatusCodeSame(403);

        foreach ([[], ["HTTP_X_CSRF_TOKEN" => "invalid"]] as $headers) {
            $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json"] + $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(403);
        }
        $client->request("POST", "/orders/draft", ["markup" => "0", "lines" => $payload["lines"], "_token" => "invalid"]);
        self::assertResponseStatusCodeSame(403);

        $client->request("POST", "/orders/draft", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode(["lines" => $payload["lines"]], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $connection->executeStatement("DELETE FROM identity_user_permissions WHERE user_id = ? AND permission_id = ?", [990001, $permissionId]);
        try {
            $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode($payload, JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(403);
            self::assertSame($count, (int) $connection->fetchOne("SELECT COUNT(*) FROM orders"));
        } finally {
            $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        }
    }

    public function testAnonymousOrderRequestsRequireLoginBeforeDtoParsing(): void {
        $client = static::createClient();
        $client->request("GET", "/orders/new");
        self::assertResponseRedirects("http://localhost/login");
        $client->request("POST", "/orders", []);
        self::assertResponseRedirects("http://localhost/login");
    }

    public function testJsonCreateRequiresServerDraftEvenWhenLinesAreSubmitted(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 220");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);
        $crawler = $client->request("GET", "/orders/new");
        $token = $crawler->filter('input[name="_token"]')->attr("value");
        $count = (int) $connection->fetchOne("SELECT COUNT(*) FROM orders");

        $client->request("POST", "/orders", server: ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token], content: json_encode([
            "customerId" => 19, "desiredAt" => "2026-10-21", "lines" => [["productId" => 15, "quantity" => "1"]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame("content-type", "application/json");
        self::assertStringContainsString("Состав заказа отсутствует", json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)["message"]);
        self::assertSame($count, (int) $connection->fetchOne("SELECT COUNT(*) FROM orders"));
    }
}
