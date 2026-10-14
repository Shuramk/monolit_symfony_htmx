<?php declare(strict_types=1);

namespace App\Tests\Domain\Warehouse;

use App\Domain\Product\UseCases\Dtos\SaveProductDto;
use App\Domain\Product\UseCases\SaveProduct;
use App\Domain\Warehouse\ReadModel\WarehouseView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WarehouseViewTest extends KernelTestCase {
    public function testReservesAggregateDuplicateLinesAndMultipleShipmentsWithoutClosedOrdersOrNegativeRemainders(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            $save = self::getContainer()->get(SaveProduct::class);
            $group = $save->execute(new SaveProductDto('Warehouse fixture', unit:'м²'), 990001);
            $product = $save->execute(new SaveProductDto('серый'), 990001, parentId:$group);
            $db->update('products', ['storage_office'=>'2.000','storage_workshop'=>'3.000','storage_third'=>'100.000'], ['id'=>$product]);
            $order = $db->fetchAssociative('SELECT * FROM orders LIMIT 1');
            $item = $db->fetchAssociative('SELECT * FROM order_items LIMIT 1');
            unset($item['id']);
            $item['product_id'] = $product;
            foreach (['new', 'cancelled', 'paid_and_delivered', 'in_progress', 'partially_shipped'] as $index => $status) {
                $orderId = 990080001 + $index;
                $order['id'] = $orderId;
                $order['status'] = $status;
                $db->insert('orders', $order);
                $item['order_id'] = $orderId;
                $item['position'] = 1;
                $item['quantity'] = [10,50,60,4,1][$index];
                $db->insert('order_items', $item);
            }
            $item['order_id'] = 990080001;
            $item['position'] = 2;
            $item['quantity'] = 5;
            $db->insert('order_items', $item);
            foreach ([[990080010,990080001,3], [990080011,990080001,2], [990080012,990080004,6]] as [$shipmentId,$orderId,$quantity]) {
                $db->insert('order_shipments', ['id'=>$shipmentId,'order_id'=>$orderId,'imported_at'=>'2026-10-08 12:00:00']);
                $db->insert('order_shipment_items', ['shipment_id'=>$shipmentId,'product_id'=>$product,'quantity'=>$quantity]);
            }
            $view = self::getContainer()->get(WarehouseView::class)->page('', $group, -20);
            self::assertSame(1, $view->total);
            self::assertSame(1, $view->page);
            self::assertSame('11.000', $view->products[0]['reserved']);
            self::assertSame('5.000', $view->products[0]['total']);
            self::assertSame('-6.000', $view->products[0]['free']);
            self::assertSame('м²', $view->products[0]['unit']);
            self::assertSame(0, self::getContainer()->get(WarehouseView::class)->page('not-a-product', $group, 999)->total);
            self::assertSame('100.000', $db->fetchOne('SELECT storage_third FROM products WHERE id = ?', [$product]));
        } finally {
            $db->rollBack();
            $em->clear();
        }
    }
}
