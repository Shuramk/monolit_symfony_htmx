<?php declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\OrderDetailsView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class OrderDetailsViewTest extends KernelTestCase {
    public function testCardDisplaysVatFopAndUntaxedPricesWithoutApplyingMarkupTwice(): void {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/orders/99003002'));
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $connection->beginTransaction();
        try {
            $productId = (int) $connection->fetchOne('SELECT id FROM products ORDER BY id LIMIT 1');
            $connection->insert('orders', [
                'id' => 99003002, 'customer_name' => 'Заказчик', 'created_at' => '2026-10-08 12:00:00',
                'desired_at' => '2026-10-09 00:00:00', 'status' => 'new', 'shipping_cost' => '3.00',
                'prepayment' => '10.00', 'markup' => 20, 'imported_at' => '2026-10-08 12:00:00', 'updated_at' => '2026-10-08 12:00:00',
            ]);
            $connection->insert('order_items', [
                'order_id' => 99003002, 'product_id' => $productId, 'product_name' => 'серый', 'product_group_name' => 'Плитка',
                'unit' => 'м²', 'quantity' => '2.000', 'unit_price' => '25.0000', 'position' => 1,
            ]);
            $connection->insert('order_shipments', ['id' => -99003002, 'order_id' => 99003002, 'imported_at' => '2026-10-08 12:00:00']);
            $connection->insert('order_shipment_items', ['shipment_id' => -99003002, 'product_id' => $productId, 'quantity' => '1.000']);
            $connection->insert('order_payments', ['source' => 'shipment', 'source_id' => 99003002, 'order_id' => 99003002, 'amount' => '5.00', 'paid_at' => '2026-10-08 12:00:00', 'imported_at' => '2026-10-08 12:00:00']);
            foreach ([
                20 => ['30.000', '50.00', '10.00', '60.00', '3.60', '48.60', '30.00'],
                10 => ['27.5000', '55.00', '0.00', '55.00', '3.30', '43.30', '27.50'],
                0 => ['25.0000', '50.00', '0.00', '50.00', '3.00', '38.00', '25.00'],
            ] as $markup => $expected) {
                $connection->update('orders', ['markup' => $markup], ['id' => 99003002]);
                $order = self::getContainer()->get(OrderDetailsView::class)->find(99003002);
                $html = self::getContainer()->get('twig')->render('order/show.html.twig', ['order' => $order]);
                $crawler = new Crawler($html);
                if ($markup === 20) {
                    self::assertStringContainsString('с НДС', $crawler->filter('.legacy-order-lines thead')->text());
                    self::assertStringContainsString('Бн 20%:', $crawler->filter('.legacy-order-lines tfoot')->text());
                    self::assertStringContainsString('Итого с бн:', $crawler->filter('.legacy-order-lines tfoot')->text());
                } elseif ($markup > 0) {
                    self::assertStringContainsString('с наценкой ФОП', $crawler->filter('.legacy-order-lines thead')->text());
                    self::assertStringContainsString('ФОП 10%', $crawler->filter('.legacy-order-markup')->text());
                } else {
                    self::assertCount(0, $crawler->filter('.legacy-order-markup'));
                }
                self::assertCount($markup > 0 ? 8 : 7, $crawler->filter('.legacy-order-lines > thead > tr > th'));
                self::assertSame('25.0000', $order->lines[0]['unitPrice']);
                self::assertSame($markup, $order->markup);
                self::assertSame($expected, [$order->lines[0]['unitPriceWithMarkup'], $order->goodsTotal, $order->vatAmount, $order->goodsTotalWithMarkup, $order->shippingCost, $order->remainingAmount, $order->shippedAmount]);
                self::assertSame('15.00', $order->paidAmount);
                self::assertSame('5.00', $order->shipmentPayment);
            }
        } finally {
            $connection->rollBack();
            self::getContainer()->get('request_stack')->pop();
        }
    }

    public function testWeightAndWorkshopAvailabilityMatchLegacyAndReservationsDoNotMultiply(): void {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/orders/99003003'));
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $save = self::getContainer()->get(\App\Domain\Product\UseCases\SaveProduct::class);
            $group = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('Order stock fixture', unit: 'м²', weightPerUnit: '900.123'), 990001);
            $product = $save->execute(new \App\Domain\Product\UseCases\Dtos\SaveProductDto('серый'), 990001, parentId: $group);
            $db->update('products', ['storage_office'=>'100.000','storage_workshop'=>'3.000','storage_third'=>'200.000', 'enabled'=>0], ['id'=>$product]);
            $db->update('products', ['enabled'=>0], ['id'=>$group]);
            foreach (['new', 'in_progress', 'cancelled', 'paid_and_delivered', 'partially_shipped'] as $index => $status) {
                $id = 99003003 + $index;
                $db->insert('orders', ['id'=>$id, 'customer_name'=>'Stock fixture', 'status'=>$status, 'created_at'=>'2026-10-09 12:00:00', 'desired_at'=>'2026-10-10 00:00:00', 'imported_at'=>'2026-10-09 12:00:00', 'updated_at'=>'2026-10-09 12:00:00']);
                $db->insert('order_items', ['order_id'=>$id, 'product_id'=>$product, 'product_name'=>'серый', 'product_group_name'=>'Order stock fixture', 'unit'=>'м²', 'quantity'=>[10,4,50,60,1][$index], 'unit_price'=>'1.0000', 'position'=>1]);
            }
            $db->insert('order_items', ['order_id'=>99003003, 'product_id'=>$product, 'product_name'=>'серый', 'unit'=>'м²', 'quantity'=>'5.000', 'unit_price'=>'1.0000', 'position'=>2]);
            foreach ([[99003003,99003003,3], [99003004,99003003,2], [99003005,99003007,6]] as [$shipment,$order,$quantity]) {
                $db->insert('order_shipments', ['id'=>-$shipment,'order_id'=>$order,'imported_at'=>'2026-10-09 12:00:00']);
                $db->insert('order_shipment_items', ['shipment_id'=>-$shipment,'product_id'=>$product,'quantity'=>$quantity]);
            }
            foreach ([[1,2,0,7],[2,2,0,2],[3,2,1,50],[4,1,0,60]] as [$task,$type,$status,$quantity]) {
                $db->insert('warehouse_shipping_tasks', ['id'=>-99003000-$task, 'type'=>$type, 'status'=>$status]);
                $db->insert('warehouse_shipping_items', ['task_id'=>-99003000-$task,'product_id'=>$product,'quantity'=>$quantity]);
            }
            $view = self::getContainer()->get(OrderDetailsView::class);
            $order = $view->find(99003003);
            self::assertArrayHasKey('weight', $order->lines[0]);
            self::assertSame('9.001', $order->lines[0]['weight']);
            self::assertSame('4.501', $order->lines[1]['weight']);
            self::assertSame('13.502', $order->totalWeight);
            self::assertSame('3.00', $order->lines[0]['stock']);
            self::assertSame('-6.00', $order->lines[0]['freeStock']);
            self::assertSame('14.00', $order->lines[0]['reserved']);
            self::assertCount(2, $order->lines[0]['stockOrders']);
            self::assertSame(['10.00','4.00'], array_column($order->lines[0]['stockOrders'], 'quantity'));
            $html = new Crawler(self::getContainer()->get('twig')->render('order/show.html.twig', ['order'=>$order]));
            self::assertStringContainsString('Вес', $html->filter('.legacy-order-lines thead')->text());
            self::assertStringContainsString('Склад*', $html->filter('.legacy-order-lines thead')->text());
            self::assertSame('13.502т', $html->filter('.legacy-order-weight-total')->text());
            self::assertStringContainsString('(свободные)', $html->filter('.legacy-order-stock-legend')->text());
            self::assertCount(2, $html->filter('.legacy-order-stock [aria-expanded="false"]'));
            self::assertCount(4, $html->filter('.legacy-order-stock [role="dialog"] a'));
            self::assertCount(2, array_unique($html->filter('.legacy-order-stock [role="dialog"]')->each(static fn(Crawler $node) => $node->attr('id'))));
            $db->update('products', ['weight_per_unit'=>'1.000'], ['id'=>$group]);
            $db->update('order_items', ['quantity'=>'0.400'], ['order_id'=>99003003]);
            $order = $view->find(99003003);
            self::assertSame('0.000', $order->lines[0]['weight']);
            self::assertSame('0.001', $order->totalWeight, 'Round only after summing the exact line weights.');
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
            self::getContainer()->get('request_stack')->pop();
        }
    }
}
