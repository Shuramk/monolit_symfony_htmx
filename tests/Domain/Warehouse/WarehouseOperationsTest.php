<?php declare(strict_types=1);

namespace App\Tests\Domain\Warehouse;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\UseCases\SaveProduct;
use App\Domain\Product\UseCases\Dtos\SaveProductDto;
use App\Domain\Warehouse\ReadModel\WarehouseView;
use App\Domain\Warehouse\UseCases\ChangeStock;
use App\Domain\Warehouse\UseCases\ProductionTasks;
use App\Domain\Warehouse\UseCases\SetMinimum;
use App\Domain\Warehouse\UseCases\Dtos\ChangeStockDto;
use App\Domain\Warehouse\UseCases\Dtos\CreateProductionDto;
use App\Domain\Warehouse\UseCases\Dtos\ProductionLineDto;
use App\Domain\Warehouse\UseCases\Dtos\CompleteProductionDto;
use App\Domain\Warehouse\UseCases\Dtos\AcceptProductionDto;
use App\Domain\Warehouse\UseCases\Dtos\SetMinimumDto;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WarehouseOperationsTest extends KernelTestCase {
    private \Doctrine\DBAL\Connection $db;
    private int $group;
    private int $product;

    protected function setUp(): void {
        self::bootKernel();
        $this->db = self::getContainer()->get('doctrine.dbal.app_connection');
        $this->db->beginTransaction();
        $save = self::getContainer()->get(SaveProduct::class);
        $this->group = $save->execute(new SaveProductDto('Warehouse operations fixture'),990001);
        $this->product = $save->execute(new SaveProductDto('fixture',price:'123.4567'),990001,parentId:$this->group);
    }
    protected function tearDown(): void {
        $this->db->rollBack();
        self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        parent::tearDown();
    }
    private function key(): string { return substr(bin2hex(random_bytes(8)),0,8).'-'.substr(bin2hex(random_bytes(4)),0,4).'-4'.substr(bin2hex(random_bytes(4)),0,3).'-a'.substr(bin2hex(random_bytes(4)),0,3).'-'.bin2hex(random_bytes(6)); }
    private function stock(): string { return $this->db->fetchOne('SELECT storage_workshop FROM products WHERE id = ?',[$this->product]); }
    private function recipe(string $output = '4'): void {
        $material = (int)$this->db->fetchOne('SELECT MAX(id)+1 FROM raw_materials');
        $this->db->insert('raw_materials',['id'=>$material,'name'=>'fixture','ratio'=>'2','production'=>1]);
        $recipe = (int)$this->db->fetchOne('SELECT MAX(id)+1 FROM production_recipes');
        $this->db->insert('production_recipes',['id'=>$recipe,'product_id'=>$this->product,'press_id'=>1,'output_quantity'=>$output,'components'=>json_encode([$material=>'5'])]);
    }

    public function testStockChangesAreAdditiveIdempotentAndDoNotChangePrices(): void {
        $change = self::getContainer()->get(ChangeStock::class);
        $dto = new ChangeStockDto('2.125',$this->key(),comment:'приход');
        $one = $change->execute($this->product,$dto,990001,true,true);
        self::assertSame($one,$change->execute($this->product,$dto,990001,true,true));
        self::assertSame('2.125',$this->stock());
        $change->execute($this->product,new ChangeStockDto('3',$this->key()),990001,false,true);
        self::assertSame('-0.875',$this->stock());
        self::assertSame('123.4567',$this->db->fetchOne('SELECT price FROM products WHERE id = ?',[$this->product]));
        self::assertSame(2,(int)$this->db->fetchOne('SELECT COUNT(*) FROM warehouse_history WHERE product_id = ?',[$this->product]));
        try { $change->execute($this->product,new ChangeStockDto('8',$dto->operationId),990001,true,true); self::fail('Changed retry was accepted'); }
        catch (\DomainException $error) { self::assertStringContainsString('Ключ',$error->getMessage()); }
        self::assertSame('-0.875',$this->stock());
    }

    public function testProductionReceiptConsumesOnlyRecipeMaterialsOnce(): void {
        $this->recipe();
        $change = self::getContainer()->get(ChangeStock::class);
        $dto = new ChangeStockDto('3',$this->key(),shift:211,date:'2026-10-08');
        $result = $change->execute($this->product,$dto,990001,true,true);
        $change->execute($this->product,$dto,990001,true,true);
        self::assertSame('3.000',$this->stock());
        $rows = $this->db->fetchAllAssociative('SELECT * FROM raw_material_history WHERE warehouse_history_id = ?',[$result['id']]);
        self::assertCount(1,$rows);
        self::assertSame('-1.875',$rows[0]['quantity']);
        self::assertSame('2026-10-08 23:59:00',$rows[0]['created_at']);
        self::assertSame(990001,(int)$rows[0]['user_id']);
    }

    public function testInvalidRecipeRollsBackStockAndHistoryTogether(): void {
        $this->recipe('0');
        $change = self::getContainer()->get(ChangeStock::class);
        try { $change->execute($this->product,new ChangeStockDto('1',$this->key(),shift:211,date:'2026-10-08'),990001,true,true); self::fail('Invalid recipe accepted'); }
        catch (\DomainException $error) { self::assertStringContainsString('выход',$error->getMessage()); }
        self::assertSame('0.000',$this->stock());
        self::assertSame(0,(int)$this->db->fetchOne('SELECT COUNT(*) FROM warehouse_history WHERE product_id = ?',[$this->product]));
    }

    public function testMissingRecipeKeepsLegacyReceiptAndReturnsWarning(): void {
        $result = self::getContainer()->get(ChangeStock::class)->execute($this->product,new ChangeStockDto('1',$this->key(),shift:211,date:'2026-10-08'),990001,true,true);
        self::assertSame('1.000',$this->stock());
        self::assertStringContainsString('без списания сырья',implode(' ',$result['warnings']));
    }

    public function testPartialAcceptanceRetryAndCoverClosure(): void {
        $this->recipe();
        $production = self::getContainer()->get(ProductionTasks::class);
        $create = new CreateProductionDto([new ProductionLineDto($this->product,'5')],$this->key(),'2026-10-08');
        $ids = $production->create($this->group,$create,990001);
        self::assertSame($ids,$production->create($this->group,$create,990001));
        $done = new CompleteProductionDto('4',$this->key(),'2026-10-08');
        $entry = $production->complete($ids[0],$done,990001);
        self::assertSame($entry,$production->complete($ids[0],$done,990001));
        $accept = new AcceptProductionDto($this->key(),quantity:'2');
        $production->accept($entry,$accept,990001,true);
        $production->accept($entry,$accept,990001,true);
        self::assertSame('2.000',$this->stock());
        self::assertSame('2.000',$this->db->fetchOne('SELECT quantity FROM production_entries WHERE id = ?',[$entry]));
        $production->accept($entry,new AcceptProductionDto($this->key(),quantity:'2'),990001,true);
        self::assertSame('4.000',$this->stock());
        self::assertSame('4.000',$this->db->fetchOne('SELECT SUM(quantity) FROM production_entries WHERE task_id = ?',[$ids[0]]));
        $last = $production->complete($ids[0],new CompleteProductionDto('1',$this->key(),'2026-10-08'),990001);
        $production->closeCover($last,990001);
        $production->closeCover($last,990001);
        self::assertSame('4.000',$this->stock());
        self::assertSame(0,(int)$this->db->fetchOne('SELECT COUNT(*) FROM production_entries WHERE task_id = ? AND accepted_by IS NULL',[$ids[0]]));
        self::assertSame(2,(int)$this->db->fetchOne('SELECT COUNT(*) FROM warehouse_history WHERE product_id = ?',[$this->product]));
    }

    public function testMinimumIsVersionedAndClearingIsSupported(): void {
        $minimum = self::getContainer()->get(SetMinimum::class);
        $version = (int)$this->db->fetchOne('SELECT edit_version FROM products WHERE id = ?',[$this->product]);
        $set = $minimum->execute($this->product,new SetMinimumDto('10',$version),990001);
        self::assertSame('10.000',$set['quantity']);
        $clear = $minimum->execute($this->product,new SetMinimumDto('',$set['version']),990001);
        self::assertSame('',$clear['quantity']);
        self::assertSame('0.000',$this->stock());
    }

    public function testShippingTaskReserveIsNotTheOrderReserveAndIncludesThirdStorage(): void {
        $this->db->update('products',['storage_office'=>'1.001','storage_workshop'=>'2.002','storage_third'=>'3.003','min_quantity'=>'10'],['id'=>$this->product]);
        $this->db->insert('warehouse_shipping_tasks',['type'=>2,'status'=>0]);
        $task = $this->db->lastInsertId();
        foreach (['1.125','0.875'] as $quantity) $this->db->insert('warehouse_shipping_items',['task_id'=>$task,'product_id'=>$this->product,'quantity'=>$quantity]);
        $this->db->insert('production_entries',['product_id'=>$this->product,'quantity'=>'1.333']);
        $view = self::getContainer()->get(WarehouseView::class)->details($this->group);
        self::assertCount(1,$view->products);
        $row = $view->products[0];
        self::assertSame('6.006',$row['stock']);
        self::assertSame('4.01',$row['unallocated']);
        self::assertSame('2.00',$row['task_quantity']);
        self::assertSame('0.00',$row['reserved']);
        self::assertSame('8.665',$row['needed']);
        self::assertCount(1,$view->tasks[$this->product]);
    }
}
