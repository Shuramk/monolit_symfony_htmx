<?php declare(strict_types=1);

namespace App\Tests\Domain\Order\Entities;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Customer\Entities\Customer;
use App\Domain\Product\Entities\Product;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Entities\OrderPayment;
use App\Domain\Order\Exceptions\OrderVersionConflict;
use App\Domain\Order\ValueObjects\OrderItemData;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use App\Domain\Order\UseCases\Dtos\UpdateOrderDto;
use App\Domain\Order\UseCases\Dtos\UpdateOrderLineDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase {
    #[DataProvider('paymentStates')]
    public function testMarkupRuleUsesPaymentsAndPrepayment(array $amounts, string $prepayment, bool $paid): void {
        $order = $this->persistedOrder();
        (new \ReflectionProperty($order, 'prepayment'))->setValue($order, $prepayment);
        $payments = (new \ReflectionProperty($order, 'payments'))->getValue($order);
        foreach ($amounts as $amount) {
            // Historical payments (including refunds) are hydrated without the new-payment constructor.
            $payment = (new \ReflectionClass(OrderPayment::class))->newInstanceWithoutConstructor();
            (new \ReflectionProperty($payment, 'amount'))->setValue($payment, $amount);
            $payments->add($payment);
        }

        self::assertSame($paid, $order->hasPayments());
        $order->assertEditable(0, 0);
        if ($paid) {
            $this->expectException(\DomainException::class);
        }
        $order->assertEditable(0, 20);
    }

    public static function paymentStates(): iterable {
        yield 'empty' => [[], '0.00', false];
        yield 'zero payment' => [['0.00'], '0.00', false];
        yield 'negative payment' => [['-100.00'], '0.00', false];
        yield 'positive payment' => [['0.01'], '0.00', true];
        yield 'refunded but positive payment exists' => [['100.00', '-100.00'], '0.00', true];
        yield 'mixed payments' => [['0.00', '-10.00', '5.00'], '0.00', true];
        yield 'positive prepayment' => [[], '0.01', true];
        yield 'negative prepayment' => [[], '-10.00', false];
    }

    public function testUpdateFromDtoPreservesHistoricalProductAndAppliesDiscount(): void {
        $order = $this->persistedOrder();
        $item = $order->getItem(10);
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new UpdateOrderDto(0, $now, [new UpdateOrderLineDto(1, '3.500', '200', '10', 10)]);
        $dto->products = [1 => $this->product(1, 'Новое название', 'Новая группа', 'шт', '999')];

        self::assertTrue($order->update($dto, $now));
        self::assertSame($item, $order->getItem(10));
        self::assertSame('серый', $item->getProductSnapshot()['name']);
        self::assertSame('Плитка', $item->getProductSnapshot()['group_name']);
        self::assertSame('м²', $item->getProductSnapshot()['unit']);
        self::assertSame('3.500', $item->getQuantity()->format());
        self::assertSame('180.0000', $item->getUnitPrice()->format());
        $order->assertEditable(1, 0);
    }

    public function testUpdateWithoutChangesNeedsNoCatalogEntryOrVersionIncrement(): void {
        $order = $this->persistedOrder();
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new UpdateOrderDto(0, $now, [new UpdateOrderLineDto(1, '2', '100', lineId: 10)]);

        self::assertFalse($order->update($dto, $now));
        $order->assertEditable(0, 0);
    }

    public function testUpdateCanKeepAnUnlinkedHistoricalProduct(): void {
        $order = $this->order([new OrderItemData(0, null, Decimal::fromString('2', 3), Decimal::fromString('100', 4), Decimal::zero(2), ['name' => 'Старый товар', 'group_name' => null, 'unit' => 'шт'])]);
        (new \ReflectionProperty($order->getItems()[0], 'id'))->setValue($order->getItems()[0], 10);
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new UpdateOrderDto(0, $now, [new UpdateOrderLineDto(0, '3', '100', lineId: 10)]);

        self::assertTrue($order->update($dto, $now));
        self::assertNull($order->getItem(10)->getProductId());
        self::assertSame('Старый товар', $order->getItem(10)->getProductSnapshot()['name']);
    }

    public function testUpdateReplacesProductAndAddsAnotherPositionFromLoadedData(): void {
        $order = $this->persistedOrder();
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new UpdateOrderDto(0, $now, [
            new UpdateOrderLineDto(2, '3', '250', lineId: 10),
            new UpdateOrderLineDto(2, '1', '200'),
        ]);
        $dto->products = [2 => $this->product(2, 'красный', price: '300')];

        self::assertTrue($order->update($dto, $now));
        self::assertCount(2, $order->getItems());
        self::assertSame(10, $order->getItems()[0]->getId());
        self::assertSame(2, $order->getItems()[0]->getProductId());
        self::assertSame('красный', $order->getItems()[0]->getProductSnapshot()['name']);
        self::assertNull($order->getItems()[1]->getId());
        self::assertSame('200.0000', $order->getItems()[1]->getUnitPrice()->format());
    }

    public function testInvalidDtoDoesNotPartiallyMutateItems(): void {
        $now = new \DateTimeImmutable('2026-10-07');
        foreach ([
            [],
            [new UpdateOrderLineDto(1, '9', '200', lineId: 999)],
            [new UpdateOrderLineDto(1, '9', '200', lineId: 10), new UpdateOrderLineDto(1, '1', '100', lineId: 10)],
            [new UpdateOrderLineDto(1, '9', '200', lineId: 10), new UpdateOrderLineDto(2, '1', '100')],
        ] as $lines) {
            $order = $this->persistedOrder();
            $item = $order->getItem(10);
            try {
                $order->update(new UpdateOrderDto(0, $now, $lines), $now);
                self::fail('Expected invalid update rejection.');
            } catch (\DomainException) {
                self::assertCount(1, $order->getItems());
                self::assertSame($item, $order->getItem(10));
                self::assertSame('2.000', $item->getQuantity()->format());
                self::assertSame('100.0000', $item->getUnitPrice()->format());
                $order->assertEditable(0, 0);
            }
        }
    }

    public function testUpdateChecksVersionBeforeResolvingProducts(): void {
        $order = $this->persistedOrder();
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new UpdateOrderDto(1, $now, [new UpdateOrderLineDto(999, '1', '100')]);

        $this->expectException(OrderVersionConflict::class);
        $order->update($dto, $now);
    }

    public function testCreationFromDtoUsesLoadedProductAndDefaultPrice(): void {
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new CreateOrderDto(1, $now, null, null, '0', 0, [
            new CreateOrderLineDto(15, '2.500', null, '10'),
            new CreateOrderLineDto(15, '1', '0', '0'),
        ]);
        $dto->products = [15 => $this->product(15, price: '435.0000')];

        $order = Order::create(26100701, new Customer(1, 'Клиент', null, $now), $dto, $now);

        self::assertCount(2, $order->getItems());
        self::assertSame([1, 2], array_map(static fn ($item): int => $item->getPosition(), $order->getItems()));
        self::assertSame(15, $order->getItems()[0]->getProductId());
        self::assertSame('серый', $order->getItems()[0]->getProductSnapshot()['name']);
        self::assertSame('2.500', $order->getItems()[0]->getQuantity()->format());
        self::assertSame('391.5000', $order->getItems()[0]->getUnitPrice()->format());
        self::assertSame('0.0000', $order->getItems()[1]->getUnitPrice()->format());
    }

    public function testCreationRejectsAnUnloadedProduct(): void {
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new CreateOrderDto(1, $now, null, null, '0', 0, [new CreateOrderLineDto(15, '1')]);

        $this->expectException(\DomainException::class);
        Order::create(26100701, new Customer(1, 'Клиент', null, $now), $dto, $now);
    }

    public function testCreationRejectsADisabledProductLoadedByRepository(): void {
        $now = new \DateTimeImmutable('2026-10-08');
        $product = $this->product();
        (new \ReflectionProperty($product, 'enabled'))->setValue($product, false);
        $dto = new CreateOrderDto(1, $now, null, null, '0', 0, [new CreateOrderLineDto(1, '1')]);
        $dto->products = [1 => $product];

        $this->expectException(\DomainException::class);
        Order::create(26100801, new Customer(1, 'Клиент', null, $now), $dto, $now);
    }

    public function testUpdateRejectsANewProductFromADisabledGroup(): void {
        $now = new \DateTimeImmutable('2026-10-08');
        $product = $this->product(2);
        (new \ReflectionProperty($product->getParent(), 'enabled'))->setValue($product->getParent(), false);
        $dto = new UpdateOrderDto(0, $now, [new UpdateOrderLineDto(2, '1', '100', lineId: 10)]);
        $dto->products = [2 => $product];

        $this->expectException(\DomainException::class);
        $this->persistedOrder()->update($dto, $now);
    }

    public function testCreationRejectsAMismatchedProductId(): void {
        $now = new \DateTimeImmutable('2026-10-07');
        $dto = new CreateOrderDto(1, $now, null, null, '0', 0, [new CreateOrderLineDto(15, '1')]);
        $dto->products = [15 => $this->product(16)];

        $this->expectException(\DomainException::class);
        Order::create(26100701, new Customer(1, 'Клиент', null, $now), $dto, $now);
    }

    public function testCreationCalculatesDiscountWithoutFloats(): void {
        $order = $this->order([$this->line(price: '435', discount: '10')]);

        self::assertSame(26100701, $order->getId());
        self::assertSame(1, $order->getCustomer()->getId());
        self::assertSame('391.5000', $order->getItems()[0]->getUnitPrice()->format());
        self::assertSame('2.000', $order->getItems()[0]->getQuantity()->format());
    }

    public function testNoOpKeepsVersionAndIdentityButChangeIncrementsVersion(): void {
        $order = $this->persistedOrder();
        $item = $order->getItem(10);
        self::assertFalse($this->revise($order, [$this->line(id: 10)]));
        $order->assertEditable(0, 0);
        self::assertTrue($this->revise($order, [$this->line(id: 10, quantity: '3.500')]));
        self::assertSame($item, $order->getItem(10));
        self::assertSame('3.500', $item->getQuantity()->format());
        $order->assertEditable(1, 0);
        $this->expectException(OrderVersionConflict::class);
        $order->assertEditable(0, 0);
    }

    public function testPaidOrderRejectsMarkupChange(): void {
        $order = $this->persistedOrder();
        (new \ReflectionProperty($order, 'prepayment'))->setValue($order, '10.00');
        $this->expectException(\DomainException::class);
        $order->assertEditable(0, 20);
    }

    public function testReorderingKeepsItemsAndExposesTheirNewOrder(): void {
        $order = $this->order([$this->line(), $this->line(quantity: '3')]);
        foreach ($order->getItems() as $index => $item) {
            (new \ReflectionProperty($item, 'id'))->setValue($item, 10 + $index);
        }
        self::assertTrue($this->revise($order, [$this->line(id: 11, quantity: '3'), $this->line(id: 10)]));
        self::assertSame([11, 10], array_map(static fn ($item): ?int => $item->getId(), $order->getItems()));
        self::assertSame([1, 2], array_map(static fn ($item): int => $item->getPosition(), $order->getItems()));
    }

    public function testEmptyOrderIsRejected(): void {
        $this->expectException(\DomainException::class);
        $this->order([]);
    }

    public function testEmptyEditDoesNotModifyExistingItems(): void {
        $order = $this->persistedOrder();
        $item = $order->getItem(10);
        try {
            $this->revise($order, []);
            self::fail('Expected empty order rejection.');
        } catch (\DomainException) {
            self::assertSame($item, $order->getItem(10));
            $order->assertEditable(0, 0);
        }
    }

    public function testDuplicateAndForeignItemIdsDoNotMutateTheOrder(): void {
        $order = $this->persistedOrder();
        foreach ([[$this->line(id: 10), $this->line(id: 10)], [$this->line(id: 999)]] as $invalid) {
            try {
                $this->revise($order, $invalid);
                self::fail('Expected invalid line rejection.');
            } catch (\DomainException) {
                self::assertCount(1, $order->getItems());
                self::assertSame('2.000', $order->getItem(10)->getQuantity()->format());
            }
        }
    }

    public function testInvalidDeliveryDoesNotPartiallyChangeItems(): void {
        $order = $this->persistedOrder();
        try {
            $order->revise(0, new \DateTimeImmutable('2026-10-07'), null, null, Decimal::fromString('-1', 2), 0, [$this->line(id: 10, quantity: '9')], new \DateTimeImmutable());
            self::fail('Expected invalid delivery rejection.');
        } catch (\DomainException) {
            self::assertSame('2.000', $order->getItem(10)->getQuantity()->format());
            $order->assertEditable(0, 0);
        }
    }

    #[DataProvider('invalidValues')]
    public function testInvalidLineValuesAreRejected(string $quantity, string $price, string $discount): void {
        $this->expectException(\DomainException::class);
        $this->line(quantity: $quantity, price: $price, discount: $discount);
    }

    public static function invalidValues(): iterable {
        yield 'zero quantity' => ['0', '10', '0'];
        yield 'negative quantity' => ['-1', '10', '0'];
        yield 'negative price' => ['1', '-10', '0'];
        yield 'discount too high' => ['1', '10', '101'];
        yield 'discount too low' => ['1', '10', '-101'];
    }

    private function order(array $items): Order {
        $now = new \DateTimeImmutable('2026-10-07 00:00:00');

        return new Order(26100701, new Customer(1, 'Клиент', null, $now), $now, null, null, Decimal::zero(2), 0, $items, $now);
    }

    private function persistedOrder(): Order {
        $order = $this->order([$this->line()]);
        // Simulate the generated identifier assigned by Doctrine hydration/persistence.
        (new \ReflectionProperty($order->getItems()[0], 'id'))->setValue($order->getItems()[0], 10);

        return $order;
    }

    private function line(int $id = 0, string $quantity = '2', string $price = '100', string $discount = '0'): OrderItemData {
        return new OrderItemData($id, $this->product(), Decimal::fromString($quantity, 3), Decimal::fromString($price, 4), Decimal::fromString($discount, 2));
    }

    private function product(int $id = 1, string $name = 'серый', string $group = 'Плитка', string $unit = 'м²', string $price = '100'): Product {
        $now = new \DateTimeImmutable('2026-10-08');
        $parent = new Product(1000, $group, $unit, Decimal::zero(4), $now);

        return new Product($id, $name, $unit, Decimal::fromString($price, 4), $now, $parent);
    }

    private function revise(Order $order, array $items): bool {
        return $order->revise(0, new \DateTimeImmutable('2026-10-07'), null, null, Decimal::zero(2), 0, $items, new \DateTimeImmutable());
    }
}
