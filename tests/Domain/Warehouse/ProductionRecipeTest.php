<?php declare(strict_types=1);

namespace App\Tests\Domain\Warehouse;

use App\Domain\Warehouse\Entities\ProductionRecipe;
use App\Domain\Common\ValueObjects\Decimal;
use PHPUnit\Framework\TestCase;

final class ProductionRecipeTest extends TestCase {
    public function testExactConsumptionUsesRatioAndSkipsNonProductionMaterials(): void {
        $recipe=new ProductionRecipe('3',[1=>'1',2=>'999']);
        $result=$recipe->consumption(Decimal::fromString('1.125',3),[1=>['ratio'=>'2','production'=>1],2=>['ratio'=>'0','production'=>0]]);
        self::assertSame('0.188',$result[1]->format());
        self::assertArrayNotHasKey(2,$result);
    }
    public function testZeroRatioIsRejected(): void {
        $this->expectException(\DomainException::class);
        (new ProductionRecipe('1',[1=>'1']))->consumption(Decimal::fromString('1',3),[1=>['ratio'=>'0','production'=>1]]);
    }
    public function testOverflowIsRejectedInsteadOfTruncatingTheMaterialLedger(): void {
        $this->expectException(\DomainException::class);
        (new ProductionRecipe('0.000001',[1=>'999999999999999']))->consumption(Decimal::fromString('99999999',3),[1=>['ratio'=>'0.000001','production'=>1]]);
    }
}
