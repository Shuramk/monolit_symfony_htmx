<?php declare(strict_types=1);

namespace App\Tests\Domain\Crm\ReadModel;

use App\Domain\Crm\ReadModel\CrmBoardView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CrmBoardViewTest extends KernelTestCase {
    public function testBoardUsesLegacyGoodsAmountsManagerFiltersAndExcludesFullyShippedOrders(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $order = $db->fetchAssociative('SELECT * FROM orders ORDER BY id LIMIT 1');
            $manager = (int)$db->fetchOne('SELECT id FROM identity_users ORDER BY id LIMIT 1');
            $product = (int)$db->fetchOne('SELECT id FROM products WHERE parent_id IS NOT NULL ORDER BY id LIMIT 1');
            $order = array_replace($order, ['id'=>990101, 'customer_name'=>'Board money fixture', 'manager_id'=>$manager, 'comments'=>'Board money fixture',
                'status'=>'new', 'prepayment'=>'100.00', 'shipping_cost'=>'500.00', 'markup'=>20, 'desired_at'=>'2026-10-12 14:00:00']);
            foreach ([990101=>'new',990102=>'in_progress',990103=>'cancelled',990104=>'paid_and_delivered'] as $id=>$status) {
                $db->insert('orders', array_replace($order, ['id'=>$id,'status'=>$status]));
                $db->insert('order_items', ['order_id'=>$id,'product_id'=>$product,'product_name'=>'fixture','product_group_name'=>'fixture','unit'=>'м²','quantity'=>'2.500','unit_price'=>'123.4500','position'=>1]);
            }
            $db->insert('order_shipments', ['id'=>990105,'order_id'=>990102,'imported_at'=>'2026-10-12 10:00:00']);
            $db->insert('order_shipment_items', ['shipment_id'=>990105,'product_id'=>$product,'quantity'=>'2.500']);
            $db->insert('warehouse_shipping_tasks', ['id'=>990106,'order_id'=>990101,'status'=>0,'type'=>2,'performance_date'=>'2026-10-12 14:00:00','payload'=>'{"allowed":1,"collected":1}']);
            $db->insert('warehouse_shipping_items', ['id'=>990107,'task_id'=>990106,'product_id'=>$product,'quantity'=>'1.000','price'=>'999.0000','payload'=>'{}']);
            $db->insert('crm_deals', ['id'=>990108,'author_id'=>$manager,'comment'=>'Board money fixture','director_comment'=>'','created_at'=>'2026-10-11 10:00:00','budget'=>'7.00']);
            $db->insert('crm_deal_contacts', ['deal_id'=>990108,'executor_id'=>$manager,'due_at'=>'2026-10-11 10:00:00','comment'=>'']);
            $now = new \DateTimeImmutable('2026-10-12 12:00:00',new \DateTimeZone('Europe/Kyiv'));
            $view = self::getContainer()->get(CrmBoardView::class);
            $board = $view->get('active','Board money fixture',[$manager],$now);
            self::assertSame([990101], array_map('intval',array_column($board['orders'],'id')));
            self::assertSame('370.35',$board['orderTotal']);
            self::assertSame('270.35',$board['unpaidTotal']);
            self::assertSame('148.14',$board['approvedTotal']);
            self::assertSame('7.00',$board['dealTotal']);
            self::assertSame('today',$board['approved'][0]['deadline']['class']);
            self::assertSame('overdue',$board['deals'][0]['deadline']['class']);
            self::assertSame('-1 дн.',$board['deals'][0]['deadline']['label']);
            self::assertTrue($board['approved'][0]['collected']);
            $other = $view->get('active','Board money fixture',[$manager+990000],$now);
            self::assertSame([], $other['deals']);
            self::assertSame([], $other['orders']);
            self::assertSame([], $other['approved']);
        } finally {
            $db->rollBack();
        }
    }
}
