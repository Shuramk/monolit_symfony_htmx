<?php declare(strict_types=1);
namespace App\Tests\Domain\Warehouse;

use App\Domain\Product\UseCases\Dtos\SaveProductDto;
use App\Domain\Product\UseCases\SaveProduct;
use App\Domain\Warehouse\ReadModel\WarehouseView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WarehouseOverviewTest extends KernelTestCase {
    public function testInventoryPermissionPreservesLegacyGroupsAndUserException(): void {
        foreach ([[9000,\App\Domain\Identity\Enums\UserRole::Main,true],[9000,\App\Domain\Identity\Enums\UserRole::Production,true],[9000,\App\Domain\Identity\Enums\UserRole::Sales,false],[31,\App\Domain\Identity\Enums\UserRole::Partner,true],[31,null,false],[9000,null,false]] as [$id,$role,$expected]) {
            $user = new \App\Domain\Identity\Entities\User($id, 'fixture', 'Fixture', null, true);
            $user->setRole($role);
            self::assertSame($expected, $user->canPrintWarehouseInventory());
        }
    }

    public function testOverviewMatchesLegacyAggregationAndInventoryIncludesAllVariants(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            $save = self::getContainer()->get(SaveProduct::class);
            $group = $save->execute(new SaveProductDto('Warehouse overview fixture', unit:'м<sup>2</sup>', type:1, sorting:20, forms:'1, 2', pallet0:'19.2', pallet1:'20', pallet2:'0.960', pallet3:'20.4', pallet4:'17', pallet5:'1.200'), 990001);
            $empty = $save->execute(new SaveProductDto('Warehouse overview empty', type:1, sorting:10), 990001);
            $hidden = $save->execute(new SaveProductDto('Warehouse overview hidden', type:1, enabled:false), 990001);
            $cast = $save->execute(new SaveProductDto('Warehouse overview cast', type:2, forms:'39.09'), 990001);
            $one = $save->execute(new SaveProductDto('серый'), 990001, parentId:$group);
            $two = $save->execute(new SaveProductDto('скрытый', enabled:false), 990001, parentId:$group);
            $db->update('products', ['storage_office'=>'2.000','storage_workshop'=>'3.000','storage_third'=>'999.000'], ['id'=>$one]);
            $db->update('products', ['storage_office'=>'-1.000','storage_workshop'=>'4.000'], ['id'=>$two]);
            $view = self::getContainer()->get(WarehouseView::class);
            $overview = $view->overview();
            $rows = array_values(array_filter($overview->sections[1], static fn ($row): bool => in_array((int)$row['id'], [$group,$empty,$hidden], true)));
            self::assertSame([$empty,$group], array_map(static fn ($row): int => (int)$row['id'], $rows));
            self::assertSame('0', $rows[0]['total']);
            self::assertSame('8', $rows[1]['total']);
            self::assertSame('1', $rows[1]['office']);
            self::assertSame('7', $rows[1]['workshop']);
            self::assertSame('м²', $rows[1]['unit']);
            self::assertSame('1, 2', $rows[1]['forms']);
            $castRows = array_values(array_filter($overview->sections[2], static fn ($row): bool => (int)$row['id'] === $cast));
            self::assertSame('39.09', $castRows[0]['forms']);
            $detail = $view->page('', $group, 1);
            self::assertSame(2, $detail->total);
            self::assertSame([$one,$two], array_map(static fn ($row): int => (int)$row['id'], $detail->products));
            $debug = self::getContainer()->get('doctrine.debug_data_holder');
            $debug->reset();
            $inventory = $view->inventory(1);
            $queries = array_filter($debug->getData()['app'] ?? [], static fn ($q): bool => preg_match('/\bFROM products\b/i', $q['sql']) === 1);
            self::assertCount(2, $queries);
            $groups = array_values(array_filter($inventory->groups, static fn ($row): bool => (int)$row['id'] === $group));
            self::assertSame([$one,$two], array_map(static fn ($row): int => (int)$row['id'], $groups[0]['variants']));
            self::assertSame(['стандарт','не стандарт'], array_column($groups[0]['palletRows'], 'label'));
            self::assertSame('19.2', $groups[0]['palletRows'][0]['total']);
            self::assertSame('20', $groups[0]['palletRows'][0]['rows']);
            self::assertNull($view->findGroup($hidden));
            self::assertSame('999.000', $db->fetchOne('SELECT storage_third FROM products WHERE id = ?', [$one]));
        } finally {
            $db->rollBack();
            $em->clear();
        }
    }
}
