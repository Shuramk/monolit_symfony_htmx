<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CrmControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testCreationAcceptsTheActualCollapsedFormFieldsAndAnEmptyEstimatedBudget(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            foreach ([1700,1701] as $right) {
                $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            }
            $crawler = $client->request('GET','/crm/deals/new');
            $form = $crawler->filter('#crm-deal-form')->form();
            $values = $form->getPhpValues();
            $values['customerId'] = (string)$db->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
            $values['comment'] = 'Native deal form fixture';
            self::assertSame('',$values['budget']);
            self::assertArrayHasKey('contactAt',$values);
            self::assertArrayNotHasKey('toOrder',$values);
            $client->jsonRequest('POST','/crm/deals',$values,['HTTP_X_CSRF_TOKEN'=>$values['_token']]);
            self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(),true)['id'];
            self::assertSame('0.00',$db->fetchOne('SELECT budget FROM crm_deals WHERE id=?',[$id]));
        } finally {
            $db->rollBack();
        }
    }

    public function testCrmUsesFourScrollableCardColumnsInsteadOfTheDealTable(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=1700');
            for ($i = 0; $i < 35; ++$i) {
                $db->insert('crm_deals', ['author_id'=>990001, 'comment'=>'Board fixture '.$i, 'director_comment'=>'', 'budget'=>'5.00', 'created_at'=>'2026-10-12 10:00:00']);
            }
            $crawler = $client->request('GET', '/crm/deals?q=Board+fixture');
            self::assertResponseIsSuccessful();
            self::assertCount(4, $crawler->filter('.crm-board-column'));
            self::assertCount(35, $crawler->filter('[data-column="deals"] .crm-board-card'));
            self::assertCount(0, $crawler->filter('table.crm-list'));
            self::assertStringContainsString('175.00', $crawler->filter('[data-column="deals"] .crm-column-heading')->text());
            self::assertCount(4, $crawler->filter('.crm-counter-plus'));
            self::assertCount(1, $crawler->filter('.crm-board-filter summary'));
        } finally {
            $db->rollBack();
        }
    }

    public function testShipmentApprovalMovesCardsAndChecksCsrfPermissionsAndStaleState(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $db->insert('warehouse_shipping_tasks', ['id'=>990091, 'type'=>2, 'status'=>0, 'comment'=>'Board shipment fixture', 'payload'=>'{"allowed":null,"collected":1}']);
            $client->jsonRequest('POST', '/crm/shipments/990091/approval', ['allowed'=>1, 'expectedAllowed'=>0]);
            self::assertResponseStatusCodeSame(403);
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=1700');
            $crawler = $client->request('GET', '/crm/deals?q=Board+shipment+fixture');
            self::assertCount(1, $crawler->filter('[data-column="shipments"] [data-shipment-id="990091"]'));
            self::assertCount(1, $crawler->filter('[data-shipment-id="990091"] .crm-ready-check'));
            $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('[name="_token"]')->first()->attr('value')];
            $client->jsonRequest('POST', '/crm/shipments/990091/approval', ['allowed'=>1, 'expectedAllowed'=>0]);
            self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST', '/crm/shipments/990091/approval', ['allowed'=>1, 'expectedAllowed'=>0], $headers);
            self::assertResponseIsSuccessful();
            $crawler = $client->request('GET', '/crm/deals?q=Board+shipment+fixture');
            self::assertCount(0, $crawler->filter('[data-column="shipments"] [data-shipment-id="990091"]'));
            self::assertCount(1, $crawler->filter('[data-column="approved"] [data-shipment-id="990091"]'));
            $client->jsonRequest('POST', '/crm/shipments/990091/approval', ['allowed'=>0, 'expectedAllowed'=>0], $headers);
            self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST', '/crm/shipments/990091/approval', ['allowed'=>0, 'expectedAllowed'=>1], $headers);
            self::assertResponseIsSuccessful();
            $payload = json_decode($db->fetchOne('SELECT payload FROM warehouse_shipping_tasks WHERE id=990091'), true);
            self::assertSame(1, $payload['collected']);
            self::assertNull($payload['allowed']);
            $db->update('warehouse_shipping_tasks', ['status'=>1], ['id'=>990091]);
            $client->jsonRequest('POST', '/crm/shipments/990091/approval', ['allowed'=>1, 'expectedAllowed'=>0], $headers);
            self::assertResponseStatusCodeSame(409);
        } finally {
            $db->rollBack();
        }
    }

    public function testCrmListsRequireTheirOwnPermissions(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            foreach (['/crm/deals'=>1700,'/crm/tasks'=>1750,'/crm/tasks/archive'=>1754,'/crm/director/tasks'=>1755,'/crm/director/tasks/archive'=>1759] as $url=>$permission) {
                $client->request('GET',$url); self::assertResponseStatusCodeSame(403);
                $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$permission]);
                $client->request('GET',$url); self::assertResponseIsSuccessful();
            }
        } finally { $db->rollBack(); }
    }

    public function testDealLifecycleAndStaleVersionPreserveHistory(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            foreach ([1700,1701] as $right) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            $crawler = $client->request('GET','/crm/deals/new'); self::assertResponseIsSuccessful();
            self::assertCount(1,$crawler->filter('.crm-deal-toolbar button[form="crm-deal-form"]'));
            self::assertCount(1,$crawler->filter('.crm-deal-panel'));
            self::assertCount(2,$crawler->filter('.crm-deal-section:not([open])'));
            self::assertCount(1,$crawler->filter('input[name="toOrder"][disabled]'));
            self::assertCount(1,$crawler->filter('input[type="date"][data-crm-target="contactDate"]'));
            self::assertCount(1,$crawler->filter('input[type="time"][data-crm-target="contactTime"]'));
            $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('[name="_token"]')->attr('value')];
            $customer = (int)$db->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
            $data = ['customerId'=>$customer,'comment'=>'CRM fixture','budget'=>'123.45','markup'=>0,'executorId'=>990001,'contactAt'=>'2026-10-15T10:00','desiredAt'=>'2026-10-20','lines'=>[]];
            $client->jsonRequest('POST','/crm/deals',$data); self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST','/crm/deals',$data,$headers); self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(),true)['id'];
            self::assertSame('123.45',$db->fetchOne('SELECT budget FROM crm_deals WHERE id=?',[$id]));
            $edit = $client->request('GET','/crm/deals/'.$id);
            self::assertCount(2,$edit->filter('.crm-deal-section:not([open])'));
            self::assertSame((string)$customer,$edit->filter('select[name="customerId"] option[selected]')->attr('value'));
            $client->jsonRequest('POST','/crm/deals/'.$id,array_replace($data,['version'=>0,'toOrder'=>'1','comment'=>'Must not save']),$headers);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('CRM fixture',$db->fetchOne('SELECT comment FROM crm_deals WHERE id=?',[$id]));
            foreach ([220,225] as $right) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            $client->jsonRequest('POST','/crm/deals/'.$id,array_replace($data,['version'=>0,'toOrder'=>'1','comment'=>'Must roll back']),$headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame('CRM fixture',$db->fetchOne('SELECT comment FROM crm_deals WHERE id=?',[$id]));
            self::assertSame(0,(int)$db->fetchOne('SELECT version FROM crm_deals WHERE id=?',[$id]));
            $client->jsonRequest('POST','/crm/deals/'.$id,$data+['version'=>0],$headers); self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/crm/deals/'.$id,$data+['version'=>0],$headers); self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST','/crm/deals/'.$id.'/cancel',['version'=>1,'comment'=>'Дорого','cancelType'=>1],$headers); self::assertResponseIsSuccessful();
            self::assertSame('cancelled',$db->fetchOne('SELECT state FROM crm_deals WHERE id=?',[$id]));
            $client->request('GET','/crm/deals?state=cancelled&q=CRM+fixture'); self::assertResponseIsSuccessful(); self::assertStringContainsString('CRM fixture',$client->getResponse()->getContent());
            $client->jsonRequest('POST','/crm/deals/'.$id.'/restore',['version'=>2],$headers); self::assertResponseIsSuccessful();
            self::assertSame('active',$db->fetchOne('SELECT state FROM crm_deals WHERE id=?',[$id]));
            self::assertGreaterThanOrEqual(3,(int)$db->fetchOne('SELECT COUNT(*) FROM crm_deal_history WHERE deal_id=?',[$id]));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testTasksLifecycleAndParticipantAccess(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            foreach ([1750,1751,1754,1755,1756,1759] as $right) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            $crawler = $client->request('GET','/crm/tasks/new'); self::assertResponseIsSuccessful();
            $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('[name="_token"]')->attr('value')];
            $payload = ['text'=>'CRM task fixture','executorId'=>990001,'dueAt'=>'2026-10-15T18:00','coexecutors'=>[],'observers'=>[]];
            $client->jsonRequest('POST','/crm/tasks',$payload,$headers); self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(),true)['id'];
            $client->request('GET','/crm/tasks/'.$id); self::assertResponseIsSuccessful(); self::assertStringContainsString('CRM task fixture',$client->getResponse()->getContent());
            $client->jsonRequest('POST','/crm/tasks/'.$id.'/complete',['version'=>0,'comment'=>'Готово'],$headers); self::assertResponseIsSuccessful();
            self::assertNotNull($db->fetchOne('SELECT completed_at FROM crm_tasks WHERE id=?',[$id]));
            $client->request('GET','/crm/tasks/archive'); self::assertResponseIsSuccessful(); self::assertStringContainsString('CRM task fixture',$client->getResponse()->getContent());
            $client->jsonRequest('POST','/crm/tasks/'.$id.'/reopen',['version'=>1],$headers); self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/crm/tasks/'.$id,$payload+['version'=>0],$headers); self::assertResponseStatusCodeSame(409);
            $client->jsonRequest('POST','/crm/tasks/'.$id,$payload+['version'=>2],$headers); self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/crm/tasks/'.$id.'/cancel',['version'=>3,'comment'=>'Не требуется'],$headers); self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/crm/tasks/'.$id.'/hide',['version'=>4],$headers); self::assertResponseIsSuccessful();
            $client->request('GET','/crm/director/tasks/archive?state=hidden'); self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/crm/tasks/'.$id.'/restore',['version'=>5],$headers); self::assertResponseIsSuccessful();
            self::assertGreaterThanOrEqual(5,(int)$db->fetchOne('SELECT COUNT(*) FROM crm_task_history WHERE task_id=?',[$id]));
            // Main role alone is not director access and cannot read someone else's task.
            $client->getCookieJar()->clear(); $this->authenticate($client,990003);
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990003,id FROM identity_permissions WHERE legacy_division_id=1750');
            $client->request('GET','/crm/tasks/'.$id); self::assertResponseStatusCodeSame(403);
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testConvertingDealToOrderIsAtomicAndIdempotent(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            foreach ([1700,1701,220,225] as $right) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            $crawler = $client->request('GET','/crm/deals/new'); $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('[name="_token"]')->attr('value')];
            $product = (int)$db->fetchOne('SELECT p.id FROM products p INNER JOIN products g ON g.id=p.parent_id WHERE p.enabled=1 AND g.enabled=1 ORDER BY p.id LIMIT 1');
            $data = ['customerId'=>(int)$db->fetchOne('SELECT id FROM customers LIMIT 1'),'executorId'=>990001,'contactAt'=>'2026-10-15T10:00','markup'=>20,'lines'=>[['productId'=>$product,'quantity'=>'2.500','price'=>'123.4500']]];
            $client->jsonRequest('POST','/crm/deals',$data,$headers); self::assertResponseIsSuccessful(); $id = json_decode($client->getResponse()->getContent(),true)['id'];
            self::assertSame('370.35',$db->fetchOne('SELECT budget FROM crm_deals WHERE id=?',[$id]));
            $client->jsonRequest('POST','/crm/deals/'.$id,$data+['version'=>0,'toOrder'=>'1'],$headers); self::assertResponseIsSuccessful();
            $order = (int)$db->fetchOne('SELECT converted_order_id FROM crm_deals WHERE id=?',[$id]); self::assertGreaterThan(0,$order);
            $client->jsonRequest('POST','/crm/deals/'.$id.'/convert',['version'=>0],$headers); self::assertResponseIsSuccessful();
            self::assertSame($order,(int)$db->fetchOne('SELECT converted_order_id FROM crm_deals WHERE id=?',[$id]));
            self::assertSame(1,(int)$db->fetchOne('SELECT COUNT(*) FROM order_items WHERE order_id=?',[$order]));
            self::assertGreaterThan(0,(int)$db->fetchOne('SELECT COUNT(*) FROM order_status_history WHERE order_id=?',[$order]));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }
}
