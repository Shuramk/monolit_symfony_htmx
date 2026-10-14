<?php declare(strict_types=1);

namespace App\Tests\Domain\Warehouse;

use App\Domain\Warehouse\Import\LegacyWarehouseImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LegacyWarehouseImportTest extends KernelTestCase {
    public function testRepeatingCompletedImportDoesNotTouchApplicationData(): void {
        self::bootKernel();
        $db=self::getContainer()->get('doctrine.dbal.app_connection');
        $counts=[];
        foreach (['products','warehouse_history','warehouse_shipping_tasks','warehouse_shipping_items','production_entries','raw_material_history'] as $table) $counts[$table]=$db->fetchOne('SELECT COUNT(*) FROM '.$table);
        $before=$db->fetchAssociative('SELECT id,storage_office,storage_workshop,price FROM products WHERE parent_id IS NOT NULL ORDER BY id LIMIT 1');
        $result=self::getContainer()->get(LegacyWarehouseImporter::class)->import();
        self::assertTrue($result['alreadyImported']);
        foreach ($counts as $table=>$count) self::assertSame($count,$db->fetchOne('SELECT COUNT(*) FROM '.$table));
        self::assertSame($before,$db->fetchAssociative('SELECT id,storage_office,storage_workshop,price FROM products WHERE parent_id IS NOT NULL ORDER BY id LIMIT 1'));
    }
    public function testSerializedRecipeAndZeroDatesAreNormalizedWithoutObjects(): void {
        self::assertSame([1=>'1.125'],LegacyWarehouseImporter::serialized('a:1:{i:1;s:5:"1.125";}'));
        self::assertNull(LegacyWarehouseImporter::date('0000-00-00 00:00:00'));
        self::assertSame('2026-10-08 23:59:00',LegacyWarehouseImporter::date('2026-10-08 23:59:00'));
        $this->expectException(\DomainException::class);
        LegacyWarehouseImporter::serialized('a:1:{i:0;O:8:"stdClass":0:{}}');
    }
}
