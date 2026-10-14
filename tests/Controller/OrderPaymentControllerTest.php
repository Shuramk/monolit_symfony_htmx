<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderPaymentControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testExistingPaymentSnapshotsAreHiddenButOrderChangesKeepDetails(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=220');
            $db->insert('orders',['id'=>99009031,'customer_name'=>'History fixture','status'=>'new','created_at'=>'2026-10-11 12:00:00','desired_at'=>'2026-10-21','imported_at'=>'2026-10-11 12:00:00','updated_at'=>'2026-10-11 12:00:00']);
            $db->insert('order_items',['order_id'=>99009031,'product_name'=>'History product','unit'=>'шт','quantity'=>'1','unit_price'=>'100','position'=>1]);
            $snapshot = self::getContainer()->get(\App\Domain\Order\Services\OrderChangelog::class)->snapshot(99009031);
            foreach (['created'=>'Заказ оформлен','updated'=>'Заказ редактировался','payment_added'=>'Внесена оплата 10.00 грн','delivery_payment_added'=>'Внесена оплата за доставку 20.00 грн'] as $event=>$message) {
                $db->insert('order_changelog',['order_id'=>99009031,'event'=>$event,'message'=>$message,'snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'user_id'=>990001,'created_at'=>'2026-10-11 12:00:00']);
            }
            $crawler = $client->request('GET','/orders/99009031');
            self::assertResponseIsSuccessful();
            self::assertCount(2,$crawler->filter('.legacy-order-history details'));
            foreach ($crawler->filter('.legacy-order-history details summary') as $summary) self::assertMatchesRegularExpression('/Заказ (оформлен|редактировался)/u',$summary->textContent);
            self::assertSelectorTextContains('.legacy-order-history','Внесена оплата 10.00 грн');
            self::assertSelectorTextContains('.legacy-order-history','Внесена оплата за доставку 20.00 грн');
            self::assertSame(4,(int)$db->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id=99009031'));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testDeliveryPaymentIsSeparateIdempotentAndUsesItsOwnPermission(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            foreach ([220,227,228] as $division) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?', [$division]);
            foreach ([99009021=>0,99009022=>20,99009023=>0] as $id=>$markup) {
                $db->insert('orders', ['id'=>$id,'customer_name'=>'Delivery payment fixture','status'=>$id===99009023?'cancelled':'new','markup'=>$markup,'shipping_cost'=>'50','created_at'=>'2026-10-09 12:00:00','desired_at'=>'2026-10-10','imported_at'=>'2026-10-09 12:00:00','updated_at'=>'2026-10-09 12:00:00']);
                $db->insert('order_items', ['order_id'=>$id,'product_name'=>'Payment product','unit'=>'шт','quantity'=>'1','unit_price'=>'100','position'=>1]);
            }
            $crawler = $client->request('GET','/orders/99009021');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form[action="/orders/99009021/delivery-payments"]');
            $token = $crawler->filter('[data-order-payment-csrf-value]')->first()->attr('data-order-payment-csrf-value');
            $headers = ['HTTP_X_CSRF_TOKEN'=>$token];
            $payload = ['amount'=>'20.50','toCard'=>true,'operationId'=>'12345678-1234-4123-a123-123456789121'];
            $client->jsonRequest('POST','/orders/99009021/delivery-payments',$payload);
            self::assertResponseStatusCodeSame(403);
            foreach (['0','-1','0.001','10000000000','no'] as $amount) {
                $client->jsonRequest('POST','/orders/99009021/delivery-payments',array_replace($payload,['amount'=>$amount]),$headers);
                self::assertResponseStatusCodeSame(400);
            }
            foreach ([1,2] as $_) {
                $client->jsonRequest('POST','/orders/99009021/delivery-payments',$payload,$headers);
                self::assertResponseIsSuccessful();
            }
            self::assertSame('0.00',$db->fetchOne('SELECT prepayment FROM orders WHERE id=99009021'));
            self::assertSame(1,(int)$db->fetchOne('SELECT edit_version FROM orders WHERE id=99009021'));
            self::assertSame(1,(int)$db->fetchOne('SELECT COUNT(*) FROM order_payments WHERE order_id=99009021'));
            $payment = $db->fetchAssociative('SELECT source,money_taken,to_card,payment_markup,user_id FROM order_payments WHERE order_id=99009021');
            self::assertSame('application_shipment',$payment['source']);
            self::assertSame(1,(int)$payment['money_taken']);
            self::assertSame(1,(int)$payment['to_card']);
            self::assertSame(0,(int)$payment['payment_markup']);
            self::assertSame(990001,(int)$payment['user_id']);
            self::assertSame(1,(int)$db->fetchOne("SELECT COUNT(*) FROM order_changelog WHERE order_id=99009021 AND event='delivery_payment_added'"));
            $snapshot = json_decode($db->fetchOne("SELECT snapshot FROM order_changelog WHERE order_id=99009021 AND event='delivery_payment_added'"),true,flags:JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('lines',$snapshot);
            self::assertSame('20.50',$snapshot['context']['amount']);
            $client->request('GET','/orders/99009021');
            self::assertSelectorNotExists('.legacy-order-history details');
            self::assertSelectorTextContains('.legacy-order-delivery-totals','20.50 грн');
            self::assertSelectorTextContains('.legacy-order-payment-totals','129.50 грн');
            self::assertSelectorTextContains('.legacy-order-history','Внесена оплата за доставку 20.50 грн');
            $client->request('GET','/orders/99009021/print');
            self::assertStringContainsString('129.50',$client->getResponse()->getContent());
            $client->jsonRequest('POST','/orders/99009021/payments',$payload,$headers);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/orders/99009021/delivery-payments',array_replace($payload,['amount'=>'21']),$headers);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/orders/99009022/delivery-payments',$payload,$headers);
            self::assertResponseStatusCodeSame(400);
            $goodsPayload = ['amount'=>'10','toCard'=>false,'operationId'=>'12345678-1234-4123-a123-123456789125'];
            $client->jsonRequest('POST','/orders/99009021/payments',$goodsPayload,$headers);
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST','/orders/99009021/delivery-payments',$goodsPayload,$headers);
            self::assertResponseStatusCodeSame(400);
            $db->insert('order_payments',['source'=>'shipment','source_id'=>99009021,'order_id'=>99009021,'paid_at'=>'2026-10-09 12:00:00','amount'=>'1.50','imported_at'=>'2026-10-09 12:00:00']);
            $client->request('GET','/orders/99009021');
            self::assertSelectorTextContains('.legacy-order-delivery-totals','22.00 грн');
            self::assertSelectorTextContains('.legacy-order-payment-totals','32.00 грн');
            self::assertSelectorTextContains('.legacy-order-payment-totals','118.00 грн');
            self::assertSame('10.00',$db->fetchOne('SELECT prepayment FROM orders WHERE id=99009021'));
            $client->jsonRequest('POST','/orders/99009022/delivery-payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789122']),$headers);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/orders/99009022/delivery-payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789122','toCard'=>false]),$headers);
            self::assertResponseIsSuccessful();
            self::assertSame(20,(int)$db->fetchOne('SELECT payment_markup FROM order_payments WHERE order_id=99009022'));
            $client->request('GET','/orders/99009022');
            self::assertSelectorTextContains('.legacy-order-payment-totals','159.50 грн');
            self::assertSelectorNotExists('form[action="/orders/99009022/delivery-payments"] input[name="toCard"]');
            $client->jsonRequest('POST','/orders/99009023/delivery-payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789123']),$headers);
            self::assertResponseStatusCodeSame(400);
            $client->request('GET','/orders/99009023');
            self::assertSelectorNotExists('form[action="/orders/99009023/delivery-payments"]');
            $db->executeStatement('DELETE up FROM identity_user_permissions up INNER JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=990001 AND p.legacy_division_id=227');
            $client->request('GET','/orders/99009021');
            self::assertSelectorNotExists('form[action="/orders/99009021/delivery-payments"]');
            self::assertSelectorExists('form[action="/orders/99009021/payments"]');
            $client->jsonRequest('POST','/orders/99009021/delivery-payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789124']),$headers);
            self::assertResponseStatusCodeSame(403);
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testPaymentIsAuthorizedValidatedIdempotentAndDoesNotMoveCash(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            foreach ([220,228] as $division) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?', [$division]);
            foreach ([99009001=>0,99009002=>20,99009003=>0] as $id=>$markup) {
                $db->insert('orders', ['id'=>$id,'customer_name'=>'Payment fixture','status'=>$id===99009003?'cancelled':'new','markup'=>$markup,'created_at'=>'2026-10-09 12:00:00','desired_at'=>'2026-10-10','imported_at'=>'2026-10-09 12:00:00','updated_at'=>'2026-10-09 12:00:00']);
                $db->insert('order_items', ['order_id'=>$id,'product_name'=>'Payment product','unit'=>'шт','quantity'=>'1','unit_price'=>'100','position'=>1]);
            }
            $crawler = $client->request('GET','/orders/99009001');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('dialog[data-order-payment-target="dialog"]');
            $token = $crawler->filter('[data-order-payment-csrf-value]')->attr('data-order-payment-csrf-value');
            self::assertSelectorExists('input[name="toCard"]');
            $payload = ['amount'=>'25.50','toCard'=>false,'operationId'=>'12345678-1234-4123-a123-123456789091'];
            $headers = ['HTTP_X_CSRF_TOKEN'=>$token];
            $client->jsonRequest('POST','/orders/99009001/payments',$payload);
            self::assertResponseStatusCodeSame(403);
            foreach (['0','-1','0.001','10000000000','no'] as $amount) {
                $client->jsonRequest('POST','/orders/99009001/payments',array_replace($payload,['amount'=>$amount]),$headers);
                self::assertResponseStatusCodeSame(400);
            }
            foreach ([1,2] as $_) {
                $client->jsonRequest('POST','/orders/99009001/payments',$payload,$headers);
                self::assertResponseIsSuccessful();
            }
            self::assertSame('25.50',$db->fetchOne('SELECT prepayment FROM orders WHERE id=99009001'));
            self::assertSame(1,(int)$db->fetchOne('SELECT edit_version FROM orders WHERE id=99009001'));
            self::assertSame(1,(int)$db->fetchOne("SELECT COUNT(*) FROM order_payments WHERE order_id=99009001"));
            $payment = $db->fetchAssociative('SELECT money_taken,to_card,payment_markup,user_id FROM order_payments WHERE order_id=99009001');
            self::assertSame(1,(int)$payment['money_taken']);
            self::assertSame(0,(int)$payment['payment_markup']);
            self::assertSame(0,(int)$payment['to_card']);
            self::assertSame(990001,(int)$payment['user_id']);
            self::assertSame(1,(int)$db->fetchOne("SELECT COUNT(*) FROM order_changelog WHERE order_id=99009001 AND event='payment_added'"));
            $snapshot = json_decode($db->fetchOne("SELECT snapshot FROM order_changelog WHERE order_id=99009001 AND event='payment_added'"),true,flags:JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('lines',$snapshot);
            self::assertSame('25.50',$snapshot['context']['amount']);
            $client->request('GET','/orders/99009001');
            self::assertSelectorNotExists('.legacy-order-history details');
            self::assertSelectorTextContains('.legacy-order-payment-totals','25.50 грн');
            self::assertSelectorTextContains('.legacy-order-payment-totals','74.50 грн');
            self::assertSelectorTextContains('.legacy-order-history','Внесена оплата 25.50 грн');
            $client->request('GET','/orders/99009001/print');
            self::assertStringContainsString('25.50',$client->getResponse()->getContent());
            $client->jsonRequest('POST','/orders/99009001/payments',array_replace($payload,['amount'=>'26']),$headers);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/orders/99009002/payments',$payload,$headers);
            self::assertResponseStatusCodeSame(400);
            $client->request('GET','/orders/99009002');
            self::assertSelectorNotExists('input[name="toCard"]');
            $client->jsonRequest('POST','/orders/99009002/payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789092','toCard'=>true]),$headers);
            self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/orders/99009002/payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789092']),$headers);
            self::assertResponseIsSuccessful();
            self::assertSame(20,(int)$db->fetchOne('SELECT payment_markup FROM order_payments WHERE order_id=99009002'));
            $client->request('GET','/orders/99009002');
            self::assertSelectorTextContains('.legacy-order-payment-totals','94.50 грн');
            self::assertSelectorTextContains('.legacy-order-history','Внесена оплата 25.50 грн (БН)');
            $client->jsonRequest('POST','/orders/99009003/payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789093']),$headers);
            self::assertResponseStatusCodeSame(400);
            $client->request('GET','/orders/99009003');
            self::assertSelectorNotExists('[data-action="order-payment#open"]');
            $db->executeStatement('DELETE up FROM identity_user_permissions up INNER JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=990001 AND p.legacy_division_id=228');
            $client->jsonRequest('POST','/orders/99009001/payments',array_replace($payload,['operationId'=>'12345678-1234-4123-a123-123456789094']),$headers);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('25.50',$db->fetchOne('SELECT prepayment FROM orders WHERE id=99009001'));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testFailureRollsBackPaymentTotalVersionAndHistoryTogether(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $db->insert('orders',['id'=>99009011,'customer_name'=>'Rollback fixture','status'=>'new','created_at'=>'2026-10-09 12:00:00','desired_at'=>'2026-10-10','imported_at'=>'2026-10-09 12:00:00','updated_at'=>'2026-10-09 12:00:00']);
            $repository = self::getContainer()->get(\App\Domain\Order\Repository\OrderRepository::class);
            $useCase = self::getContainer()->get(\App\Domain\Order\UseCases\AddOrderPayment::class);
            try {
                $repository->transactional(function () use ($useCase): void {
                    $useCase->execute(99009011,new \App\Domain\Order\UseCases\Dtos\AddOrderPaymentDto('10','12345678-1234-4123-a123-123456789095'),990001,new \DateTimeImmutable());
                    $useCase->execute(99009011,new \App\Domain\Order\UseCases\Dtos\AddOrderPaymentDto('20','12345678-1234-4123-a123-123456789096'),990001,new \DateTimeImmutable(), shipment: true);
                    throw new \RuntimeException('Rollback fixture');
                });
                self::fail('Expected rollback');
            } catch (\RuntimeException $exception) { self::assertSame('Rollback fixture',$exception->getMessage()); }
            self::assertSame('0.00',$db->fetchOne('SELECT prepayment FROM orders WHERE id=99009011'));
            self::assertSame(0,(int)$db->fetchOne('SELECT edit_version FROM orders WHERE id=99009011'));
            self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM order_payments WHERE order_id=99009011'));
            self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM order_changelog WHERE order_id=99009011'));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }
}
