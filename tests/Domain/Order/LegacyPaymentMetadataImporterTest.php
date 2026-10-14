<?php declare(strict_types=1);

namespace App\Tests\Domain\Order;

use App\Domain\Order\Services\LegacyPaymentMetadataImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LegacyPaymentMetadataImporterTest extends KernelTestCase {
    public function testImportOnlyRestoresMissingMetadataAndNeverReimportsOrOverwritesPayments(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $legacy = self::getContainer()->get('doctrine.dbal.legacy_connection');
        $db->beginTransaction();
        try {
            $importer = self::getContainer()->get(LegacyPaymentMetadataImporter::class);
            $importer->import();
            $payment = $db->fetchAssociative("SELECT * FROM order_payments WHERE source='order' AND money_taken=3 LIMIT 1");
            self::assertIsArray($payment);
            $original = $legacy->fetchAssociative('SELECT moneyTaken,toCard,pCashless FROM sklad_payments WHERE paymentID=?', [$payment['source_id']]);
            $totals = $db->fetchAssociative('SELECT COUNT(*) AS total,SUM(amount) AS sum FROM order_payments');
            $orders = $db->fetchAssociative('SELECT COUNT(*) AS total,SUM(prepayment) AS sum,SUM(edit_version) AS version_sum FROM orders');
            $db->update('order_payments',['metadata_imported_at'=>null,'money_taken'=>null,'to_card'=>null,'payment_markup'=>null], ['source'=>'order','source_id'=>$payment['source_id']]);
            self::assertSame(1,$importer->import()['updated']);
            $restored = $db->fetchAssociative('SELECT money_taken,to_card,payment_markup FROM order_payments WHERE source=? AND source_id=?',['order',$payment['source_id']]);
            self::assertEquals(array_values($original),array_values($restored));
            $db->update('order_payments',['money_taken'=>2],['source'=>'order','source_id'=>$payment['source_id']]);
            self::assertSame(0,$importer->import()['updated']);
            self::assertSame(2,(int)$db->fetchOne('SELECT money_taken FROM order_payments WHERE source=? AND source_id=?',['order',$payment['source_id']]));
            self::assertSame($totals,$db->fetchAssociative('SELECT COUNT(*) AS total,SUM(amount) AS sum FROM order_payments'));
            self::assertSame($orders,$db->fetchAssociative('SELECT COUNT(*) AS total,SUM(prepayment) AS sum,SUM(edit_version) AS version_sum FROM orders'));
        } finally { $db->rollBack(); }
    }

    public function testMismatchRollsBackMetadataWithoutChangingThePayment(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $payment = $db->fetchAssociative("SELECT * FROM order_payments WHERE source='order' LIMIT 1");
            $db->update('order_payments',['metadata_imported_at'=>null,'amount'=>'0.01'], ['source'=>'order','source_id'=>$payment['source_id']]);
            try {
                self::getContainer()->get(LegacyPaymentMetadataImporter::class)->import();
                self::fail('A changed payment must not receive metadata for a different amount.');
            } catch (\DomainException $exception) { self::assertStringContainsString('отличается от legacy',$exception->getMessage()); }
            $row = $db->fetchAssociative('SELECT amount,metadata_imported_at FROM order_payments WHERE source=? AND source_id=?',['order',$payment['source_id']]);
            self::assertSame('0.01',$row['amount']);
            self::assertNull($row['metadata_imported_at']);
        } finally { $db->rollBack(); }
    }
}
