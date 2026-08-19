<?php declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\Dtos\OrderSearchDto;
use PHPUnit\Framework\TestCase;

final class OrderSearchDtoTest extends TestCase {
    public function testFormValuesAreTrimmedAndCommaIsNormalized(): void {
        $dto = OrderSearchDto::fromArray(['orderId' => ' 260101 ', 'parentId' => '42', 'productId' => '99', 'quantity' => ' 12,500 ']);
        self::assertSame('260101', $dto->orderId);
        self::assertSame(42, $dto->parentId);
        self::assertSame(99, $dto->productId);
        self::assertSame('12.500', $dto->quantity);
    }

    public function testInvalidQuantityCannotReachSql(): void {
        $this->expectException(\DomainException::class);
        new OrderSearchDto(quantity: '1 OR 1=1');
    }
}
