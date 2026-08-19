<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WarehouseDetailsControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testLegacyColumnsAndFunctionalActionsArePresent(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            foreach ([101,106,320] as $division) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id = ?',[$division]);
            $id = $db->fetchOne('SELECT id FROM products WHERE parent_id IS NULL AND enabled = 1 ORDER BY sorting,id LIMIT 1');
            $crawler = $client->request('GET','/warehouse/products/'.$id);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('thead','В переложке');
            self::assertSelectorTextContains('thead','В задачах / В заказах');
            self::assertSelectorExists('.warehouse-detail-table');
            self::assertSelectorExists('[data-warehouse-minimum]');
            self::assertSelectorExists('[data-action="warehouse#openStock"]');
            self::assertSelectorExists('a.warehouse-history-link');
            $client->request('GET',$crawler->filter('a.warehouse-history-link')->first()->attr('href'));
            self::assertResponseIsSuccessful();
            self::assertCount(2, $client->getCrawler()->filter('nav.pagination'));
            $next = $client->getCrawler()->filter('nav.pagination a[rel="next"]')->first()->attr('href');
            $client->request('GET', $next);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('nav.pagination [aria-current="page"]', '2');
            self::assertSelectorExists('nav.pagination a[rel="prev"]');
            $client->request('GET','/warehouse/products/'.$id.'/production');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h2','Задачи на производство');
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testJsonCsrfValidationMinimumProductionAndAcceptanceWorkEndToEnd(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            foreach ([101,106,320] as $division) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id = ?',[$division]);
            $save = self::getContainer()->get(\App\Domain\Product\UseCases\SaveProduct::class);
            $group = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('HTTP warehouse fixture'),990001);
            $product = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('HTTP color'),990001,parentId:$group);
            $crawler = $client->request('GET','/warehouse/products/'.$group);
            self::assertResponseIsSuccessful();
            $token = $crawler->filter('[data-warehouse-csrf-value]')->attr('data-warehouse-csrf-value');
            $headers = ['HTTP_X_CSRF_TOKEN'=>$token];
            $operation = '12345678-1234-4123-a123-123456789012';
            $payload = ['quantity'=>'2.125','storage'=>'2','operationId'=>$operation];
            $client->jsonRequest('POST','/warehouse/variants/'.$product.'/add',$payload);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST','/warehouse/variants/'.$product.'/add',array_replace($payload,['quantity'=>'-1']),$headers);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/warehouse/variants/'.$product.'/add',$payload,$headers);
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/warehouse/variants/'.$product.'/add',$payload,$headers);
            self::assertResponseIsSuccessful();
            self::assertSame('2.125',$db->fetchOne('SELECT storage_workshop FROM products WHERE id = ?',[$product]));
            $version = $db->fetchOne('SELECT edit_version FROM products WHERE id = ?',[$product]);
            $client->jsonRequest('POST','/warehouse/variants/'.$product.'/minimum',['quantity'=>'12.5','version'=>(string)$version],$headers);
            self::assertResponseIsSuccessful();
            self::assertSame('12.500',$db->fetchOne('SELECT min_quantity FROM products WHERE id = ?',[$product]));
            $client->jsonRequest('POST','/warehouse/variants/'.$product.'/minimum',['quantity'=>'20','version'=>(string)$version],$headers);
            self::assertResponseStatusCodeSame(409);
            $client->request('GET','/warehouse/products/'.$group.'/need');
            self::assertResponseIsSuccessful();
            self::assertSame('10.375',json_decode($client->getResponse()->getContent(),true)['products'][0]['quantity']);
            $client->jsonRequest('POST','/warehouse/products/'.$group.'/production',['lines'=>[['productId'=>(string)$product,'quantity'=>'3']],'operationId'=>'12345678-1234-4123-a123-123456789013','date'=>'2026-10-08','press'=>'1'],$headers);
            self::assertResponseStatusCodeSame(201);
            $task = json_decode($client->getResponse()->getContent(),true)['ids'][0];
            $client->jsonRequest('POST','/warehouse/production/'.$task.'/complete',['quantity'=>'3','operationId'=>'12345678-1234-4123-a123-123456789014','date'=>'2026-10-08','shift'=>'1'],$headers);
            self::assertResponseIsSuccessful();
            $entry = json_decode($client->getResponse()->getContent(),true)['entryId'];
            $accept = ['quantity'=>'1','storage'=>'2','operationId'=>'12345678-1234-4123-a123-123456789015'];
            $client->jsonRequest('POST','/warehouse/production/entries/'.$entry.'/accept',$accept,$headers);
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/warehouse/production/entries/'.$entry.'/accept',$accept,$headers);
            self::assertResponseIsSuccessful();
            self::assertSame('3.125',$db->fetchOne('SELECT storage_workshop FROM products WHERE id = ?',[$product]));
            $client->jsonRequest('POST','/warehouse/production/entries/'.$entry.'/close',[],$headers);
            self::assertResponseIsSuccessful();
            self::assertSame('3.125',$db->fetchOne('SELECT storage_workshop FROM products WHERE id = ?',[$product]));
            $client->request('GET','/warehouse/products/'.$group.'/need/print?products[]='.$product);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('table','HTTP color');
            $db->executeStatement('DELETE up FROM identity_user_permissions up INNER JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=990001 AND p.legacy_division_id=106');
            $client->jsonRequest('POST','/warehouse/variants/'.$product.'/add',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789016']),$headers);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('3.125',$db->fetchOne('SELECT storage_workshop FROM products WHERE id = ?',[$product]));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }
}
