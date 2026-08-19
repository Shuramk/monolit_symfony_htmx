<?php declare(strict_types=1);
namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WarehouseControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testOverviewGroupAndPrintableInventoryUseOnlyNewRoutesAndRespectPermissions(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $permission = $db->fetchOne('SELECT id FROM identity_permissions WHERE legacy_division_id = 101');
        $hadPermission = (bool)$db->fetchOne('SELECT 1 FROM identity_user_permissions WHERE user_id = 990001 AND permission_id = ?', [$permission]);
        $oldRole = $db->fetchOne('SELECT role FROM identity_users WHERE id = 990001');
        try {
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id,permission_id) VALUES (990001,?)', [$permission]);
            $crawler = $client->request('GET', '/warehouse/products');
            self::assertResponseIsSuccessful();
            self::assertCount(4, $crawler->filter('.warehouse-groups-table'));
            self::assertSelectorTextContains('.warehouse-section-1 thead', 'Пресс');
            self::assertSelectorTextContains('.warehouse-section-2 thead', 'Формы');
            self::assertSelectorExists('.warehouse-section-3 details:not([open])');
            self::assertSelectorExists('.warehouse-section-4 details:not([open])');
            self::assertCount(2, $crawler->filter('.warehouse-inventory-button'));
            self::assertSelectorExists('[data-warehouse-stock-button][aria-expanded="false"]');
            $url = $crawler->filter('.warehouse-group-name')->first()->attr('href');
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('thead', 'В задачах / В заказах');
            $client->request('GET', '/warehouse/products/999999999');
            self::assertResponseStatusCodeSame(404);
            foreach ([1,2] as $type) {
                $crawler = $client->request('GET', '/warehouse/products/inventory/'.$type);
                self::assertResponseIsSuccessful();
                self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
                self::assertSelectorExists('[data-action="warehouse#print"]');
                self::assertSelectorTextContains('thead', 'Подсчет');
                foreach ($crawler->filter('tbody tr')->each(static fn ($row) => $row) as $row) {
                    if ($row->filter('td')->count() === 6) {
                        for ($column = 2; $column < 6; $column++) self::assertSame('', trim($row->filter('td')->eq($column)->text()));
                    }
                }
            }
            $client->request('GET', '/warehouse/products/inventory/3');
            self::assertResponseStatusCodeSame(404);
            $db = self::getContainer()->get('doctrine.dbal.app_connection');
            $db->update('identity_users', ['role'=>'ROLE_SALES'], ['id'=>990001]);
            // Symfony invalidates authentication when the user's security roles change.
            $this->authenticate($client);
            $client->request('GET', '/warehouse/products');
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('.warehouse-inventory-button');
            $client->request('GET', '/warehouse/products/inventory/1');
            self::assertResponseStatusCodeSame(403);
            $db = self::getContainer()->get('doctrine.dbal.app_connection');
            $db->delete('identity_user_permissions', ['user_id'=>990001,'permission_id'=>$permission]);
            $client->request('GET', '/warehouse/products');
            self::assertResponseStatusCodeSame(403);
        } finally {
            $db = self::getContainer()->get('doctrine.dbal.app_connection');
            $db->update('identity_users', ['role'=>$oldRole], ['id'=>990001]);
            if ($hadPermission) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id,permission_id) VALUES (990001,?)', [$permission]);
            else $db->delete('identity_user_permissions', ['user_id'=>990001,'permission_id'=>$permission]);
        }
    }
}
