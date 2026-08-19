<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductSettingsControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testInlineTablesParseNestedDtosAndProtectTheEntireBatch(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) SELECT 990001, id FROM identity_permissions WHERE legacy_division_id = 1010');
            $save = self::getContainer()->get(\App\Domain\Product\UseCases\SaveProduct::class);
            $group = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('HTTP inline group'), 990001);
            $id = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('серый', price:'330.1234'), 990001, parentId:$group);
            $foreignGroup = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('HTTP other group'), 990001);
            $foreign = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('чужой', price:'10'), 990001, parentId:$foreignGroup);
            $crawler = $client->request('GET', '/settings/products/'.$group);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-product-field=price]');
            self::assertSelectorExists('[data-action="product-table#adjust"]');
            $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
            $version = (int) $db->fetchOne('SELECT edit_version FROM products WHERE id = ?', [$id]);
            $row = ['id'=>(string)$id,'version'=>(string)$version,'name'=>'серый','nameUk'=>'сірий','price'=>'335.1234','sorting'=>'50','enabled'=>'0'];
            $url = '/settings/products/'.$group.'/variants';
            $client->jsonRequest('POST', $url, ['rows'=>[$row]]);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', $url, ['rows'=>[array_replace($row, ['price'=>'-1'])]], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST', $url, ['rows'=>[$row]], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseIsSuccessful();
            $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($version+1, $data['versions'][$id]);
            self::assertSame('335.1234', $db->fetchOne('SELECT price FROM products WHERE id = ?', [$id]));
            self::assertSame(0, (int) $db->fetchOne('SELECT enabled FROM products WHERE id = ?', [$id]));
            $client->jsonRequest('POST', $url, ['rows'=>[$row]], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseStatusCodeSame(409);
            $row['version'] = (string) ($version+1);
            $row['price'] = '999';
            $client->jsonRequest('POST', $url, ['rows'=>[$row, array_replace($row, ['id'=>(string)$foreign])]], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('335.1234', $db->fetchOne('SELECT price FROM products WHERE id = ?', [$id]));
            $sortVersion = (string) $db->fetchOne('SELECT edit_version FROM products WHERE id = ?', [$group]);
            $client->jsonRequest('POST', '/settings/products/sorting', ['rows'=>[['id'=>(string)$group,'version'=>$sortVersion,'sorting'=>'5']]], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseIsSuccessful();
            self::assertSame(5, (int) $db->fetchOne('SELECT sorting FROM products WHERE id = ?', [$group]));
            $client->jsonRequest('POST', '/settings/products/sorting', ['rows'=>[['id'=>(string)$group,'version'=>$sortVersion,'sorting'=>'10']]], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseStatusCodeSame(409);
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }

    public function testSettingsAndWarehouseRequireAuthentication(): void {
        $client = static::createClient();
        foreach (['/settings/products', '/warehouse/products'] as $url) {
            $client->request('GET', $url);
            self::assertResponseRedirects('/login');
        }
    }

    public function testSettingsJsonCreationValidationCsrfAndPermissions(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            foreach ([1010, 1011, 101] as $division) {
                $db->executeStatement('DELETE up FROM identity_user_permissions up INNER JOIN identity_permissions p ON p.id = up.permission_id WHERE up.user_id = 990001 AND p.legacy_division_id = ?', [$division]);
            }
            $client->request('GET', '/settings/products');
            self::assertResponseStatusCodeSame(403);
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) SELECT 990001, id FROM identity_permissions WHERE legacy_division_id = 1010');
            $client->request('GET', '/settings/products');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Настройки » Продукция');
            $client->request('GET', '/settings/products/new');
            self::assertResponseStatusCodeSame(403);
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) SELECT 990001, id FROM identity_permissions WHERE legacy_division_id = 1011');
            $crawler = $client->request('GET', '/settings/products/new');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Новая продукция');
            $client->request('GET', '/settings/products');
            self::assertSelectorTextContains('a[href="/settings/products/new"]', 'Добавить продукцию');
            $crawler = $client->request('GET', '/settings/products/new');
            $token = $crawler->filter('input[name="_token"]')->attr('value');
            $client->jsonRequest('POST', '/settings/products/new/0', ['name'=>'HTTP группа']);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', '/settings/products/new/0', ['name'=>'', 'price'=>'-1'], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST', '/settings/products/new/0', ['name'=>'HTTP группа', 'unit'=>'м²', 'type'=>'1', 'sorting'=>'100', 'enabled'=>'1'], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseStatusCodeSame(201);
            $location = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['location'];
            $crawler = $client->request('GET', $location);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('a[href*="parentId="]');
            $version = $crawler->filter('input[name="version"]')->attr('value');
            $client->jsonRequest('POST', $location, ['name'=>'HTTP группа', 'unit'=>'м²', 'type'=>'1', 'enabled'=>'0', 'version'=>$version], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseIsSuccessful();
            self::assertSame(0, (int) $db->fetchOne('SELECT enabled FROM products WHERE name = ?', ['HTTP группа']));
            $client->jsonRequest('POST', $location, ['name'=>'Конфликт', 'version'=>$version], ['HTTP_X_CSRF_TOKEN'=>$token]);
            self::assertResponseStatusCodeSame(409);
            $client->request('GET', '/warehouse/products');
            self::assertResponseStatusCodeSame(403);
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) SELECT 990001, id FROM identity_permissions WHERE legacy_division_id = 101');
            $client->request('GET', '/warehouse/products?q=серый');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Склад » Продукция');
            self::assertSelectorTextContains('.warehouse-section-1 thead', 'Пресс');
            self::assertSelectorTextContains('.warehouse-section-2 thead', 'Формы');
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
    }
}
