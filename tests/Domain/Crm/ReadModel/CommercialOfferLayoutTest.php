<?php declare(strict_types=1);

namespace App\Tests\Domain\Crm\ReadModel;

use App\Domain\Crm\ReadModel\DealDocument;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CommercialOfferLayoutTest extends KernelTestCase {
    public function testCommercialOfferRestoresLetterheadTableTotalWarrantyAndSignature(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        $path = tempnam(sys_get_temp_dir(), 'monolit-offer-layout-');
        try {
            $customer = (int) $db->fetchOne('SELECT id FROM customers ORDER BY id LIMIT 1');
            $db->update('customers', ['name' => 'Херсонська ТЕЦ & партнери'], ['id' => $customer]);
            $product = (int) $db->fetchOne('SELECT id FROM products WHERE parent_id IS NOT NULL ORDER BY id LIMIT 1');
            $db->insert('crm_deals', [
                'customer_id' => $customer, 'comment' => '', 'director_comment' => '',
                'budget' => '800040.00', 'markup' => 20, 'created_at' => '2026-10-13 12:00:00',
            ]);
            $id = (int) $db->lastInsertId();
            $db->insert('crm_deal_items', [
                'deal_id' => $id, 'product_id' => $product, 'quantity' => '100.000', 'price' => '6667.0000',
            ]);
            $bytes = self::getContainer()->get(DealDocument::class)->generate($id, 'offer', new \DateTimeImmutable('2026-10-13'));
            file_put_contents($path, $bytes);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            try {
                self::assertNotFalse($zip->getFromName('word/media/monolit-logo.png'));
                $xml = $zip->getFromName('word/document.xml');
                self::assertIsString($xml);
                $document = new \DOMDocument();
                self::assertTrue($document->loadXML($xml));
                $xpath = new \DOMXPath($document);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                self::assertStringContainsString('Директору Херсонська ТЕЦ &amp; партнери', $xml);
                self::assertStringContainsString('Виробництво', $xml);
                self::assertStringContainsString('ПП Монолит+', $xml);
                self::assertStringContainsString('Гарантія на продукцію 10 років.', $xml);
                self::assertStringContainsString('А.А. Трибрат', $xml);
                self::assertStringContainsString('800040', $xml);
                self::assertStringContainsString('rIdLogo', $zip->getFromName('word/_rels/document.xml.rels'));
                self::assertSame(1, $xpath->query('//w:p[w:r/w:t="Комерційна пропозиція"]/w:pPr/w:jc[@w:val="center"]')->length);
                self::assertSame(1, $xpath->query('//w:tr[w:tc/w:p/w:r/w:t="Разом з ПДВ:"]/w:tc/w:tcPr/w:gridSpan[@w:val="5"]')->length);
                self::assertSame('35', $xpath->evaluate('string(//w:r[w:t="Виробництво"]/w:rPr/w:sz/@w:val)'));
                self::assertSame('Arial Narrow', $xpath->evaluate('string(//w:r[w:t="Виробництво"]/w:rPr/w:rFonts/@w:hAnsi)'));
                self::assertSame('64', $xpath->evaluate('string(//w:r[w:t="ПП Монолит+"]/w:rPr/w:sz/@w:val)'));
                self::assertSame('Arial Black', $xpath->evaluate('string(//w:r[w:t="ПП Монолит+"]/w:rPr/w:rFonts/@w:hAnsi)'));
                self::assertSame('-6', $xpath->evaluate('string(//w:r[w:t="ПП Монолит+"]/w:rPr/w:position/@w:val)'));
                self::assertSame('83', $xpath->evaluate('string(//w:r[w:t="ПП Монолит+"]/w:rPr/w:w/@w:val)'));
                self::assertSame('27', $xpath->evaluate('string(//w:body/w:tbl[1]/w:tr/w:tc[3]/w:p/w:r/w:rPr/w:sz/@w:val)'));
                self::assertStringNotContainsString('Одержувач:', $xml);
                self::assertStringNotContainsString('ФОП', $xml);
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
            $db->rollBack();
        }
    }
}
