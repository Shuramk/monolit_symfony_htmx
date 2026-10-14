<?php declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\ImportLegacyShippingReadinessCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportLegacyShippingReadinessCommandTest extends KernelTestCase {
    public function testImportPreservesApprovalAndNeverOverwritesExistingReadiness(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $legacy = self::getContainer()->get('doctrine.dbal.legacy_connection');
        $db->beginTransaction();
        try {
            $id = (int)$db->fetchOne('SELECT id FROM warehouse_shipping_tasks WHERE type=2 AND status=0 ORDER BY id LIMIT 1');
            self::assertGreaterThan(0,$id);
            $expected = (int)$legacy->fetchOne('SELECT COALESCE(MAX(taskCollected),0) FROM sklad_tasks_shipment_collected WHERE taskID=?',[$id]);
            $db->update('warehouse_shipping_tasks',['payload'=>'{"allowed":1,"keep":"fixture"}'],['id'=>$id]);
            $tester = new CommandTester(self::getContainer()->get(ImportLegacyShippingReadinessCommand::class));
            self::assertSame(0,$tester->execute([]));
            $payload = json_decode($db->fetchOne('SELECT payload FROM warehouse_shipping_tasks WHERE id=?',[$id]),true);
            self::assertSame($expected,$payload['collected']);
            self::assertSame(1,$payload['allowed']);
            self::assertSame('fixture',$payload['keep']);
            $payload['collected'] = 1-$expected;
            $db->update('warehouse_shipping_tasks',['payload'=>json_encode($payload)],['id'=>$id]);
            self::assertSame(0,$tester->execute([]));
            self::assertStringContainsString('задач: 0.',$tester->getDisplay());
            self::assertSame(1-$expected,json_decode($db->fetchOne('SELECT payload FROM warehouse_shipping_tasks WHERE id=?',[$id]),true)['collected']);
        } finally {
            $db->rollBack();
        }
    }
}
