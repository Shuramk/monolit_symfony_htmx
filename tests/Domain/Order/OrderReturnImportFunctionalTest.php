<?php declare(strict_types=1);

namespace App\Tests\Domain\Order;

use App\Domain\Order\Repository\OrderReturnImportRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderReturnImportFunctionalTest extends KernelTestCase {
    public function testImportPreservesSourceAndDoesNotReplayStockOrMoneyAndRepeatDoesNotOverwrite(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $legacy = self::getContainer()->get('doctrine.dbal.legacy_connection');
        $db->beginTransaction();
        try {
            foreach (['order_return_items', 'order_return_draft_items', 'order_returns', 'order_return_drafts'] as $table) $db->executeStatement('DELETE FROM ' . $table);
            $db->update('order_return_imports', ['completed_at' => null, 'report' => null], ['id' => 'legacy-v1']);
            $before = $this->totals($db);
            $importer = self::getContainer()->get(OrderReturnImportRepository::class);
            $report = $importer->import();
            self::assertFalse($report['alreadyImported']);
            self::assertSame((int) $legacy->fetchOne('SELECT COUNT(*) FROM sklad_order_returns'), $report['returns']);
            self::assertSame((int) $legacy->fetchOne('SELECT COUNT(*) FROM sklad_order_returns_carts'), $report['items']);
            self::assertSame((int) $legacy->fetchOne('SELECT COUNT(*) FROM sklad_order_returnDraft'), $report['drafts']);
            self::assertSame($before, $this->totals($db));
            $source = $legacy->fetchAssociative('SELECT * FROM sklad_order_returns ORDER BY returnID LIMIT 1');
            $copy = $db->fetchAssociative('SELECT * FROM order_returns WHERE id=?', [$source['returnID']]);
            self::assertSame((int) $source['orderID'], (int) $copy['order_id']);
            self::assertSame($source['returnAmount'], $copy['refund_amount']);
            self::assertSame($source['returnMDate'], $copy['processed_at']);
            self::assertSame($source['userID'], $copy['processed_by']);
            self::assertSame($source, json_decode($copy['source_payload'], true, flags: JSON_THROW_ON_ERROR));
            $db->update('order_returns', ['refund_amount' => '123.45'], ['id' => $copy['id']]);
            self::assertTrue($importer->import()['alreadyImported']);
            self::assertSame('123.45', $db->fetchOne('SELECT refund_amount FROM order_returns WHERE id=?', [$copy['id']]));
            self::assertSame($before, $this->totals($db));
            foreach ($report['invalidDraftIds'] as $id) {
                self::assertNotEmpty($db->fetchOne('SELECT source_payload FROM order_return_drafts WHERE id=?', [$id]));
            }
        } finally {
            $db->rollBack();
        }
    }

    private function totals($db): array {
        return [
            $db->fetchAssociative('SELECT SUM(storage_office) AS office,SUM(storage_workshop) AS workshop FROM products'),
            $db->fetchAssociative('SELECT SUM(prepayment) AS paid,SUM(edit_version) AS version FROM orders'),
            $db->fetchOne('SELECT COUNT(*) FROM warehouse_history'),
            $db->fetchOne('SELECT COUNT(*) FROM order_changelog'),
            $db->fetchOne('SELECT SUM(quantity) FROM order_shipment_items'),
            $db->fetchOne('SELECT SUM(amount) FROM order_payments'),
        ];
    }
}
