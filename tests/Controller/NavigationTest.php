<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NavigationTest extends WebTestCase {
    use IdentityTestUser;

    public function testNavigationKeepsOrdersSelectedOnListCardAndCreation(): void {
        $client = static::createClient();
        $this->authenticate($client, 990009);
        $client->followRedirect();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $connection->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) SELECT 990009, permission.id FROM identity_permissions permission INNER JOIN identity_legacy_divisions division ON division.id = permission.legacy_division_id WHERE division.enabled = 1');
        $id = $connection->fetchOne('SELECT id FROM orders ORDER BY id DESC LIMIT 1');
        self::assertNotFalse($id);

        foreach (['/orders', '/orders/'.$id, '/orders/new'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('.app-userbar strong', 'Тестовый пользователь');
            self::assertSelectorExists('.app-userbar form[method="post"] input[name="_token"]');
            self::assertSelectorTextSame('.app-userbar button', 'Выход');
            self::assertSelectorTextSame('.app-top-tab.is-active[aria-current="true"]', 'Заказы');
            self::assertSelectorTextSame('.app-subtab.is-active[aria-current="page"]', 'Заказы');
            self::assertCount(1, $client->getCrawler()->filter('.app-top-tab.is-active'));
            self::assertCount(1, $client->getCrawler()->filter('.app-subtab.is-active'));
            self::assertStringContainsString('Задолженности', $client->getCrawler()->filter('.app-subtabs')->text());
            self::assertStringContainsString('Клиенты', $client->getCrawler()->filter('.app-subtabs')->text());
            self::assertStringNotContainsString('Главная', $client->getCrawler()->filter('.app-top-tabs')->text());
        }

        $client->request('GET', '/enterprise');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.app-top-tab.is-active', 'Настройки');
        self::assertSelectorExists('.app-subtab.is-active[href="/enterprise"]');

        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $connection->executeStatement('DELETE user_permission FROM identity_user_permissions user_permission INNER JOIN identity_permissions permission ON permission.id = user_permission.permission_id WHERE user_permission.user_id = 990009 AND permission.legacy_division_id = 200');
        $client->request('GET', '/enterprise');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.app-top-tab[href="/orders"]');
    }
}
