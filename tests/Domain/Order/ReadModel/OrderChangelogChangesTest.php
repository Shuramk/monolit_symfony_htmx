<?php declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\LegacyProductText;
use App\Domain\Order\ReadModel\OrderChangelogChanges;
use PHPUnit\Framework\TestCase;

final class OrderChangelogChangesTest extends TestCase {
    public function testOnlyChangedValuesAreShownWithCompactDecimals(): void {
        $before = $this->snapshot();
        $after = $before;
        $after['lines'][0]['quantity'] = '14.500';
        $after['lines'][0]['unitPrice'] = '111.0000';
        $changes = $this->compare($before, $after);

        self::assertSame([
            'Изменено количество: Плитка серый — 2 → 14.5 м²',
            'Изменена цена: Плитка серый — 222 → 111 грн',
        ], $changes);
    }

    public function testRemovedAndAddedProductsAreNotTreatedAsPriceChanges(): void {
        $before = $this->snapshot();
        $after = $before;
        $after['lines'][0]['productId'] = 2;
        $after['lines'][0]['name'] = 'бежевый';

        self::assertSame([
            'Удалено: Плитка серый: 2 м² по 222 грн',
            'Добавлено: Плитка бежевый: 2 м² по 222 грн',
        ], $this->compare($before, $after));
    }

    public function testLegacyDuplicatesAndReorderingDoNotCreateFalseChanges(): void {
        $before = $this->snapshot();
        $duplicate = $before['lines'][0];
        $duplicate['quantity'] = '3.000';
        $before['lines'][] = $duplicate;
        $after = $before;
        $after['lines'] = array_reverse($after['lines']);
        $after['lines'][0]['quantity'] = '3';
        $after['lines'][0]['unitPrice'] = '222';
        self::assertSame([], $this->compare($before, $after));

        $after['lines'][1]['quantity'] = '4.000';
        self::assertSame(['Изменено количество: Плитка серый — 2 → 4 м²'], $this->compare($before, $after));
    }

    public function testStableLineIdsDistinguishDuplicatesAndReplacedRows(): void {
        $before = $this->snapshot();
        $before['lines'][0]['lineId'] = 10;
        $after = $before;
        $after['lines'][0]['lineId'] = 11;
        self::assertSame([
            'Удалено: Плитка серый: 2 м² по 222 грн',
            'Добавлено: Плитка серый: 2 м² по 222 грн',
        ], $this->compare($before, $after));
    }

    public function testUnlinkedProductsAreNotAllMergedIntoOne(): void {
        $before = $this->snapshot();
        $before['lines'][0]['productId'] = null;
        $after = $before;
        $after['lines'][0]['name'] = 'бежевый';
        self::assertCount(2, $this->compare($before, $after));
    }

    public function testDeliveryMarkupCommentsAndTotalAreCompared(): void {
        $before = $this->snapshot();
        $after = $before;
        $after['delivery'] = ['address' => 'Новый адрес', 'date' => '2026-10-22', 'cost' => '123.00'];
        $after['markup'] = 20;
        $after['comments'] = 'Новый комментарий';
        $after['total'] = '345.00';
        self::assertSame([
            'Адрес доставки: — → Новый адрес',
            'Дата доставки: 21-10-2026 → 22-10-2026',
            'Стоимость доставки: 0 грн → 123 грн',
            'БН: 0% → НДС 20%',
            'Комментарий: — → Новый комментарий',
            'Итого: 444 грн → 345 грн',
        ], $this->compare($before, $after));
    }

    private function compare(array $before, array $after): array {
        return (new OrderChangelogChanges(new LegacyProductText()))->compare($before, $after);
    }

    private function snapshot(): array {
        return [
            'lines' => [['productId' => 1, 'groupName' => 'Плитка', 'name' => 'серый', 'unit' => 'м<sup>2</sup>', 'quantity' => '2.000', 'unitPrice' => '222.0000']],
            'customer' => ['name' => 'Aleks', 'phone' => '123'],
            'delivery' => ['address' => null, 'date' => '2026-10-21 00:00:00', 'cost' => '0.00'],
            'markup' => 0, 'comments' => null, 'status' => 'new', 'total' => '444.00',
        ];
    }
}
