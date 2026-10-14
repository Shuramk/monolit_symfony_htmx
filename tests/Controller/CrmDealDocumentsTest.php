<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CrmDealDocumentsTest extends WebTestCase {
    use IdentityTestUser;

    public function testProductFormDiscountDocumentsAndCancellationWorkTogether(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $client->request('GET','/crm/deals/1/documents/offer');
            self::assertResponseStatusCodeSame(403);
            foreach ([1700,1701,220,225] as $right) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            $crawler = $client->request('GET','/crm/deals/new');
            $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('[name="_token"]')->first()->attr('value')];
            self::assertCount(1,$crawler->filter('[data-action="order-form#add"]'));
            self::assertCount(1,$crawler->filter('[data-order-form-target="vatAmount"]'));
            $product = (int)$db->fetchOne('SELECT p.id FROM products p JOIN products g ON g.id=p.parent_id WHERE p.enabled=1 AND g.enabled=1 ORDER BY p.id LIMIT 1');
            $data = ['customerId'=>(int)$db->fetchOne('SELECT id FROM customers LIMIT 1'),'executorId'=>990001,'contactAt'=>'2026-10-15T10:00','markup'=>20,'lines'=>[['productId'=>$product,'quantity'=>'2.000','unitPrice'=>'100.0000','discount'=>'5']]];
            $db->update('customers',['name'=>'Заказчик & компания'],['id'=>$data['customerId']]);
            $client->jsonRequest('POST','/crm/deals',$data,$headers);
            self::assertResponseIsSuccessful();
            $id = json_decode($client->getResponse()->getContent(),true)['id'];
            self::assertSame('95.0000',$db->fetchOne('SELECT price FROM crm_deal_items WHERE deal_id=?',[$id]));
            self::assertSame('228.00',$db->fetchOne('SELECT budget FROM crm_deals WHERE id=?',[$id]));
            $crawler = $client->request('GET','/crm/deals/'.$id);
            self::assertCount(1,$crawler->filter('a[href="/crm/deals/'.$id.'/documents/invoice"]'));
            self::assertCount(1,$crawler->filter('a[href="/crm/deals/'.$id.'/documents/offer"]'));
            self::assertCount(1,$crawler->filter('a.crm-new-order[href="/orders/new"]'));
            self::assertCount(1,$crawler->filter('select[data-order-line-parent] option[selected]'));
            self::assertSame('95.0000',$crawler->filter('input[name="lines[0][unitPrice]"]')->attr('value'));
            self::assertSame('0',$crawler->filter('input[name="lines[0][discount]"]')->attr('value'));

            foreach (['invoice','offer'] as $kind) {
                $client->request('GET','/crm/deals/'.$id.'/documents/'.$kind);
                self::assertResponseIsSuccessful();
                self::assertResponseHeaderSame('Content-Type','application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                self::assertStringContainsString('attachment;',$client->getResponse()->headers->get('Content-Disposition'));
                $xml = $this->documentXml($client->getResponse()->getContent());
                self::assertStringContainsString($kind === 'invoice' ? 'Рахунок на оплату' : 'Комерційна пропозиція',$xml);
                self::assertStringContainsString($kind === 'invoice' ? '228.00' : '228', $xml);
                self::assertStringContainsString('Заказчик &amp; компания',$xml);
                if ($kind === 'invoice') self::assertStringContainsString('38.00',$xml);
            }
            self::assertSame(0,(int)$db->fetchOne('SELECT version FROM crm_deals WHERE id=?',[$id]));
            $db->update('products',['enabled'=>0],['id'=>$product]);
            $crawler = $client->request('GET','/crm/deals/'.$id);
            self::assertStringContainsString('отключён',$crawler->filter('select[name="lines[0][productId]"] option[selected]')->text());
            $client->jsonRequest('POST','/crm/deals/'.$id,$data+['version'=>0],$headers);
            self::assertResponseIsSuccessful();
            $invalid = $data;
            $invalid['lines'][0]['discount'] = '101';
            $client->jsonRequest('POST','/crm/deals/'.$id,$invalid+['version'=>1],$headers);
            self::assertResponseStatusCodeSame(400);
            self::assertSame(1,(int)$db->fetchOne('SELECT version FROM crm_deals WHERE id=?',[$id]));

            foreach ([0,10] as $markup) {
                $client->jsonRequest('POST','/crm/deals/'.$id,array_replace($data,['markup'=>$markup,'version'=>$markup === 0 ? 1 : 2]),$headers);
                self::assertResponseIsSuccessful();
                $client->request('GET','/crm/deals/'.$id.'/documents/invoice');
                if ($markup === 0) {
                    self::assertResponseStatusCodeSame(400);
                } else {
                    self::assertResponseIsSuccessful();
                    $xml = $this->documentXml($client->getResponse()->getContent());
                    self::assertStringContainsString('209.00',$xml);
                    self::assertStringContainsString('104.5000',$xml);
                }
            }
            $client->jsonRequest('POST','/crm/deals/'.$id.'/cancel',['version'=>3,'cancelType'=>1],$headers);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Дорого',(string)$db->fetchOne('SELECT details FROM crm_deal_history WHERE deal_id=? ORDER BY id DESC LIMIT 1',[$id]));
            $client->request('GET','/crm/deals/'.$id.'/documents/offer');
            self::assertResponseStatusCodeSame(400);
            $crawler = $client->request('GET','/crm/deals/'.$id);
            self::assertCount(0,$crawler->filter('.crm-deal-documents a'));
            $client->request('GET','/crm/deals/999999999/documents/offer');
            self::assertResponseStatusCodeSame(404);
            $db->insert('crm_deals',['author_id'=>990001,'customer_id'=>$data['customerId'],'comment'=>'','director_comment'=>'','budget'=>'0.00','created_at'=>'2026-10-12 12:00:00']);
            $empty = $db->lastInsertId();
            $client->request('GET','/crm/deals/'.$empty.'/documents/offer');
            self::assertResponseStatusCodeSame(400);
        } finally {
            $db->rollBack();
        }
    }

    private function documentXml(string $contents): string {
        $path = tempnam(sys_get_temp_dir(),'monolit-doc-test-');
        try {
            file_put_contents($path,$contents);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            self::assertNotFalse($zip->getFromName('[Content_Types].xml'));
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            self::assertIsString($xml);
            $document = new \DOMDocument();
            self::assertTrue($document->loadXML($xml));

            return $xml;
        } finally {
            unlink($path);
        }
    }
}
