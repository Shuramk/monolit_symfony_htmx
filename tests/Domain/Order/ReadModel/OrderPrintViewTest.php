<?php declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\OrderPrintView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderPrintViewTest extends KernelTestCase {
    public function testPrintUsesExactPricesMarkupVatDeliveryAndBothPayments(): void {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $connection->beginTransaction();
        try {
            $connection->update('enterprise_profiles', ['address' => 'Николаев,<br>Приміська,64'], ['id' => 1]);
            $connection->insert('orders', [
                'id' => 99003001, 'customer_name' => 'Заказчик <script>alert(1)</script>', 'customer_phone' => '0670000000',
                'shipping_address' => 'Адрес', 'created_at' => '2026-10-08 12:00:00', 'desired_at' => '2026-10-09 00:00:00',
                'status' => 'new', 'shipping_cost' => '3.00', 'prepayment' => '10.00', 'markup' => 0,
                'manager_id' => 999999998, 'imported_at' => '2026-10-08 12:00:00', 'updated_at' => '2026-10-08 12:00:00',
            ]);
            $connection->insert('order_items', [
                'order_id' => 99003001, 'product_name' => 'серый', 'product_group_name' => 'Плитка',
                'unit' => 'м<sup>2</sup>', 'quantity' => '2.500', 'unit_price' => '10.0000', 'position' => 1,
            ]);
            $connection->insert('order_payments', [
                'source' => 'shipment', 'source_id' => 99003001, 'order_id' => 99003001,
                'paid_at' => '2026-10-08 12:00:00', 'amount' => '5.00', 'imported_at' => '2026-10-08 12:00:00',
            ]);
            $view = self::getContainer()->get(OrderPrintView::class);
            foreach ([0 => ['10.00', '3.00', '28.00', null, '28.00', '13.00'], 10 => ['11.00', '3.30', '30.80', null, '30.80', '15.80'], 20 => ['10.00', '3.00', '28.00', '5.60', '33.60', '18.60']] as $markup => $expected) {
                $connection->update('orders', ['markup' => $markup], ['id' => 99003001]);
                $print = $view->find(99003001);
                self::assertNotNull($print);
                self::assertSame($expected, [$print->lines[0]['unitPrice'], $print->lines[1]['total'], $print->subtotal, $print->vat, $print->total, $print->remaining]);
                self::assertSame('15.00', $print->paid);
                self::assertSame('Плитка серый', $print->lines[0]['name']);
                self::assertSame('м²', $print->lines[0]['unit']);
                self::assertSame('2.50', $print->lines[0]['quantity']);
                self::assertSame('Пользователь #999999998', $print->managerName);
                self::assertSame("'08' октября 2026 г.", $print->createdDate);
                $html = self::getContainer()->get('twig')->render('order/print.html.twig', ['order' => $print]);
                self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
                self::assertStringNotContainsString('<script>alert(1)</script>', $html);
                self::assertStringContainsString('<div>Николаев,</div><div>Приміська,64</div>', $html);
                self::assertStringNotContainsString('&lt;br&gt;', $html);
                self::assertStringContainsString('(067) 000-00-00', $html);
                self::assertStringContainsString('images/monolit-logo', $html);
                self::assertStringContainsString('images/order-paid', $html);
                self::assertStringContainsString('В случае самовывоза или доставки', $html);
                self::assertStringNotContainsString('<span>Адрес:</span>', $html);
                self::assertStringContainsString('class="order-print-customer-name"', $html);
                self::assertStringContainsString('class="order-print-customer-signature"', $html);
            }
            $connection->update('orders', ['shipping_cost' => '0.00', 'markup' => 0, 'prepayment' => '30.00'], ['id' => 99003001]);
            $print = $view->find(99003001);
            self::assertCount(1, $print->lines);
            self::assertSame('-10.00', $print->remaining);
            self::assertNull($view->find(999999999));
        } finally {
            $connection->rollBack();
        }
    }
}
