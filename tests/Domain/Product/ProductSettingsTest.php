<?php declare(strict_types=1);

namespace App\Tests\Domain\Product;

use App\Domain\Product\Exceptions\ProductVersionConflict;
use App\Domain\Product\UseCases\Dtos\SaveProductDto;
use App\Domain\Product\UseCases\SaveProduct;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductSettingsTest extends KernelTestCase {
    public function testCreatesAndEditsVariantsWithInheritanceAndAuditWithoutTouchingBalancesOrOrders(): void {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            $save = $container->get(SaveProduct::class);
            $group = $save->execute(new SaveProductDto('Тестовая группа', unit:'м²', type:1), 990001);
            $variant = $save->execute(new SaveProductDto('серый', unit:'шт', price:'123.4567', type:3), 990001, parentId:$group);
            self::assertSame('м²', $db->fetchOne('SELECT unit FROM products WHERE id = ?', [$variant]));
            self::assertSame(1, (int) $db->fetchOne('SELECT type FROM products WHERE id = ?', [$variant]));
            $db->update('products', ['storage_office'=>'-3.250','storage_workshop'=>'10.500','storage_third'=>'7.000'], ['id'=>$variant]);
            $beforeOrders = $db->fetchOne('SELECT SUM(unit_price) FROM order_items');
            $version = (int) $db->fetchOne('SELECT edit_version FROM products WHERE id = ?', [$variant]);
            $save->execute(new SaveProductDto('серый', price:'200.1234', enabled:false, version:$version), 990001, $variant);
            $row = $db->fetchAssociative('SELECT * FROM products WHERE id = ?', [$variant]);
            self::assertSame('200.1234', $row['price']);
            self::assertSame(0, (int) $row['enabled']);
            self::assertSame('-3.250', $row['storage_office']);
            self::assertSame('10.500', $row['storage_workshop']);
            self::assertSame('7.000', $row['storage_third']);
            self::assertSame($beforeOrders, $db->fetchOne('SELECT SUM(unit_price) FROM order_items'));
            self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM product_changelog WHERE product_id = ?', [$variant]));
            $audit = json_decode($db->fetchOne("SELECT before_snapshot FROM product_changelog WHERE product_id = ? AND event = 'updated'", [$variant]), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('123.4567', $audit['price']);
            self::assertArrayNotHasKey('storageOffice', $audit);
            $groupVersion = (int) $db->fetchOne('SELECT edit_version FROM products WHERE id = ?', [$group]);
            $save->execute(new SaveProductDto('Тестовая группа', unit:'т', type:4, version:$groupVersion), 990001, $group);
            self::assertSame('т', $db->fetchOne('SELECT unit FROM products WHERE id = ?', [$variant]));
            self::assertSame(4, (int) $db->fetchOne('SELECT type FROM products WHERE id = ?', [$variant]));
            self::assertSame('200.1234', $db->fetchOne('SELECT price FROM products WHERE id = ?', [$variant]));
        } finally {
            $db->rollBack();
            $em->clear();
        }
    }

    public function testStaleVersionAndInvalidPalletsRollbackEverythingIncludingAllocatedId(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            $save = self::getContainer()->get(SaveProduct::class);
            $sequence = $db->fetchOne('SELECT last_id FROM product_number_sequence WHERE id = 1');
            try {
                $save->execute(new SaveProductDto('Ошибка', pallet0:'1'), 990001);
                self::fail('Incomplete pallets accepted');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('три', $exception->getMessage());
            }
            self::assertSame($sequence, $db->fetchOne('SELECT last_id FROM product_number_sequence WHERE id = 1'));
            $id = $save->execute(new SaveProductDto('Группа', unit:'шт', type:3), 990001);
            try {
                $save->execute(new SaveProductDto('Не сохранится', version:999), 990001, $id);
                self::fail('Stale version accepted');
            } catch (ProductVersionConflict) {
                self::assertSame('Группа', $db->fetchOne('SELECT name FROM products WHERE id = ?', [$id]));
            }
            $version = (int) $db->fetchOne('SELECT edit_version FROM products WHERE id = ?', [$id]);
            $save->execute(new SaveProductDto('Группа', unit:'шт', type:3, version:$version), 990001, $id);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM product_changelog WHERE product_id = ?', [$id]));
        } finally {
            $db->rollBack();
            $em->clear();
        }
    }
}
