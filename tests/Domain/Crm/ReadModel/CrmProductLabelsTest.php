<?php declare(strict_types=1);

namespace App\Tests\Domain\Crm\ReadModel;

use App\Domain\Crm\ReadModel\CrmView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CrmProductLabelsTest extends KernelTestCase {
    public function testCrmProductLabelsUsePlainTextAndUnicodeUnitsWithoutChangingStoredData(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $id = (int)$db->fetchOne('SELECT p.id FROM products p JOIN products g ON g.id=p.parent_id WHERE p.enabled=1 AND g.enabled=1 ORDER BY p.id LIMIT 1');
            $db->update('products',['name'=>'<b>Серый</b>','unit'=>'м<sup>2</sup>'],['id'=>$id]);
            $view = self::getContainer()->get(CrmView::class);
            $products = array_column($view->options()['products'],null,'id');
            self::assertSame('м²',$products[$id]['unit']);
            self::assertStringContainsString('Серый',$products[$id]['name']);
            self::assertStringNotContainsString('<b>',$products[$id]['name']);
            self::assertSame('м<sup>2</sup>',$db->fetchOne('SELECT unit FROM products WHERE id=?',[$id]));
            $db->update('products',['unit'=>'м<sup>3</sup>'],['id'=>$id]);
            $products = array_column($view->options()['products'],null,'id');
            self::assertSame('м³',$products[$id]['unit']);
        } finally {
            $db->rollBack();
        }
    }
}
