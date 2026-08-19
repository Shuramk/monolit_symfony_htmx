<?php declare(strict_types=1);

namespace App\Tests\Domain\Product;

use App\Domain\Product\Exceptions\ProductVersionConflict;
use App\Domain\Product\Repository\ProductCatalogRepository;
use App\Domain\Product\Repository\ProductSettingsRepository;
use App\Domain\Product\UseCases\Dtos\SaveProductDto;
use App\Domain\Product\UseCases\Dtos\UpdateProductSortingDto;
use App\Domain\Product\UseCases\Dtos\UpdateProductSortingRowDto;
use App\Domain\Product\UseCases\Dtos\UpdateProductVariantDto;
use App\Domain\Product\UseCases\Dtos\UpdateProductVariantsDto;
use App\Domain\Product\UseCases\SaveProduct;
use App\Domain\Product\UseCases\UpdateProductSorting;
use App\Domain\Product\UseCases\UpdateProductVariants;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductTableTest extends KernelTestCase {
    public function testVariantsAreUpdatedTogetherWithExactPricesAndMetadataPreserved(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            $save = self::getContainer()->get(SaveProduct::class);
            $group = $save->execute(new SaveProductDto('Table fixture', unit:'м²'), 990001);
            $one = $save->execute(new SaveProductDto('серый', price:'330.1234', weightPerUnit:'90', inventoryName:'Инвентарь', forms:'1, 2', minQuantity:'0.5', pallet0:'10', pallet1:'2', pallet2:'5'), 990001, parentId:$group);
            $two = $save->execute(new SaveProductDto('красный', price:'370.0001', enabled:false), 990001, parentId:$group);
            $db->update('products', ['storage_office'=>'-1.500'], ['id'=>$one]);
            $original = $db->fetchAssociative('SELECT * FROM products WHERE id = ?', [$one]);
            $v1 = (int) $original['edit_version'];
            $v2 = (int) $db->fetchOne('SELECT edit_version FROM products WHERE id = ?', [$two]);
            $update = self::getContainer()->get(UpdateProductVariants::class);
            $data = new UpdateProductVariantsDto([
                new UpdateProductVariantDto($one, $v1, 'серый новый', '335.1234', 200, false, 'сірий новий'),
                new UpdateProductVariantDto($two, $v2, 'красный', '375.0001', 50, true, 'червоний'),
            ]);
            $versions = $update->execute($group, $data, 990001);
            self::assertSame([$one=>$v1+1,$two=>$v2+1], $versions);
            $row = $db->fetchAssociative('SELECT * FROM products WHERE id = ?', [$one]);
            self::assertSame('335.1234', $row['price']);
            self::assertSame('серый новый', $row['name']);
            self::assertSame('сірий новий', $row['name_uk']);
            self::assertSame(0, (int) $row['enabled']);
            foreach (['unit','type','weight_per_unit','min_quantity','inventory_name','forms','pallets','storage_office','storage_workshop','storage_third'] as $field) {
                self::assertSame($original[$field], $row[$field], $field);
            }
            $audit = json_decode($db->fetchOne("SELECT before_snapshot FROM product_changelog WHERE product_id = ? AND event = 'updated'", [$one]), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('330.1234', $audit['price']);
            self::assertSame($two, (int) self::getContainer()->get(ProductSettingsRepository::class)->variants($group)[0]['id']);
            self::assertSame([$two], array_map(static fn ($p): int => (int) $p['id'], self::getContainer()->get(ProductCatalogRepository::class)->findProductsByParentId($group)));
            try {
                $update->execute($group, new UpdateProductVariantsDto([
                    new UpdateProductVariantDto($one, $v1+1, 'не записать', '999', 200, false),
                    new UpdateProductVariantDto($two, $v2, 'конфликт', '999', 50, true),
                ]), 990001);
                self::fail('Stale batch accepted');
            } catch (ProductVersionConflict) {
                self::assertSame('335.1234', $db->fetchOne('SELECT price FROM products WHERE id = ?', [$one]));
                self::assertSame(2, (int) $db->fetchOne("SELECT COUNT(*) FROM product_changelog WHERE product_id IN (?,?) AND event = 'updated'", [$one,$two]));
            }
        } finally {
            $db->rollBack();
            $em->clear();
        }
    }

    public function testGroupSortingChangesOnlySortingAndChangesCatalogOrder(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            $save = self::getContainer()->get(SaveProduct::class);
            $one = $save->execute(new SaveProductDto('Sort fixture one', sorting:100), 990001);
            $two = $save->execute(new SaveProductDto('Sort fixture two', sorting:200), 990001);
            $before = $db->fetchAssociative('SELECT * FROM products WHERE id = ?', [$two]);
            $version = (int) $before['edit_version'];
            self::getContainer()->get(UpdateProductSorting::class)->execute(new UpdateProductSortingDto([new UpdateProductSortingRowDto($two, $version, 10)]), 990001);
            $after = $db->fetchAssociative('SELECT * FROM products WHERE id = ?', [$two]);
            foreach (['name','unit','price','enabled','weight_per_unit','storage_office'] as $field) {
                self::assertSame($before[$field], $after[$field]);
            }
            $groups = self::getContainer()->get(ProductSettingsRepository::class)->groups('Sort fixture', true);
            self::assertSame([$two,$one], array_map(static fn ($p): int => (int) $p['id'], $groups));
            $catalogIds = array_map(static fn ($p): int => (int) $p['id'], self::getContainer()->get(ProductCatalogRepository::class)->findProductGroups());
            self::assertLessThan(array_search($one, $catalogIds, true), array_search($two, $catalogIds, true));
            self::assertSame($version+1, (int) $after['edit_version']);
        } finally {
            $db->rollBack();
            $em->clear();
        }
    }
}
