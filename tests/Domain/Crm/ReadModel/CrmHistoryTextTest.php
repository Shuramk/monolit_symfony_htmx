<?php declare(strict_types=1);

namespace App\Tests\Domain\Crm\ReadModel;

use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Crm\ReadModel\CrmHistoryText;
use App\Domain\Crm\UseCases\Dtos\SaveDealDto;
use App\Domain\Crm\UseCases\SaveDeal;
use App\Domain\Order\ReadModel\OrderDetailsView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CrmHistoryTextTest extends KernelTestCase {
    public function testOldFragmentsHandleMissingProductsEmptyListsAndMalformedData(): void {
        self::bootKernel();
        $formatter = self::getContainer()->get(CrmHistoryText::class);
        $missing = 'Продукция: [{"product_id":-9999999,"quantity":"2.000","price":"5.0000"}]';
        $broken = 'Продукция: [{"product_id":7,"quantity":"wrong","price":"5"}]';
        $texts = $formatter->format([$missing,'Продукция: []',$broken,'Комментарий: обычный текст']);
        self::assertSame('Продукция: Товар №-9999999 — 2.000 по 5.0000 грн',$texts[0]);
        self::assertSame('Продукция: не выбрана',$texts[1]);
        self::assertSame($broken,$texts[2]);
        self::assertSame('Комментарий: обычный текст',$texts[3]);
        $twig = self::getContainer()->get('twig');
        $html = $twig->createTemplate('{{ comment|nl2br }}')->render(['comment'=>"Сделка: <script>alert(1)</script>\nПродукция: Серый"]);
        self::assertStringNotContainsString('<script>',$html);
        self::assertStringContainsString('<br />',$html);
    }

    public function testNewDealHistoryAndExistingOrderHistoryDisplayProductsInsteadOfJson(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $product = (int)$db->fetchOne('SELECT p.id FROM products p JOIN products g ON g.id=p.parent_id WHERE p.enabled=1 AND g.enabled=1 ORDER BY p.id LIMIT 1');
            $db->update('products',['name'=>'Серый','unit'=>'м<sup>2</sup>'],['id'=>$product]);
            $actor = (int)$db->fetchOne('SELECT id FROM identity_users WHERE enabled=1 ORDER BY id LIMIT 1');
            $customer = (int)$db->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
            $id = self::getContainer()->get(SaveDeal::class)->execute(null,new SaveDealDto(
                customerId:$customer,lines:[['productId'=>$product,'quantity'=>'1111','price'=>'330']],
                executorId:$actor,contactAt:'2026-10-14T12:11',
            ),$actor,true,new \DateTimeImmutable('2026-10-12 15:12:54'));
            $raw = (string)$db->fetchOne('SELECT details FROM crm_deal_history WHERE deal_id=?',[$id]);
            self::assertStringNotContainsString('product_id',$raw);
            self::assertStringContainsString('Серый',$raw);
            self::assertStringContainsString('1111.000 м² по 330.0000 грн',$raw);

            $old = 'Продукция: '.json_encode([['product_id'=>$product,'quantity'=>'1111.000','price'=>'330.0000']],JSON_THROW_ON_ERROR);
            $db->executeStatement('UPDATE crm_deal_history SET details=? WHERE deal_id=?',[json_encode([$old,'Комментарий: без изменений'],JSON_UNESCAPED_UNICODE),$id]);
            $history = self::getContainer()->get(CrmRepository::class)->historyFor('deal',$id);
            self::assertStringNotContainsString('product_id',implode(' ',$history[0]['details']));
            self::assertStringContainsString('1111.000 м² по 330.0000 грн',$history[0]['details'][0]);
            self::assertSame('Комментарий: без изменений',$history[0]['details'][1]);

            $order = (int)$db->fetchOne('SELECT id FROM orders ORDER BY id LIMIT 1');
            $comment = "Сделка: Создана сделка №6508\n".$old."\nСледующий контакт: 2026-10-14 12:11:00";
            $db->insert('order_status_history',['order_id'=>$order,'status'=>'new','changed_at'=>'2026-10-12 15:12:54','comment'=>$comment,'user_id'=>$actor,'imported_at'=>'2026-10-12 15:12:54']);
            $historyId = $db->lastInsertId();
            $view = self::getContainer()->get(OrderDetailsView::class)->find($order);
            $comments = implode("\n",array_column($view->statusHistory,'comment'));
            self::assertStringContainsString('Серый',$comments);
            self::assertStringContainsString('1111.000 м² по 330.0000 грн',$comments);
            self::assertStringNotContainsString($old,$comments);
            self::assertSame($comment,$db->fetchOne('SELECT comment FROM order_status_history WHERE id=?',[$historyId]));
        } finally {
            $db->rollBack();
        }
    }
}
