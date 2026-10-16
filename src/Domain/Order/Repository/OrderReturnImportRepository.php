<?php declare(strict_types=1);

namespace App\Domain\Order\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderReturnImportRepository {
    public function __construct(
        #[Autowire('@doctrine.dbal.legacy_connection')] private Connection $legacy,
        #[Autowire('@doctrine.dbal.app_connection')] private Connection $app,
    ) {}

    public function import(): array {
        return $this->app->transactional(function (): array {
            $state = $this->app->fetchAssociative("SELECT * FROM order_return_imports WHERE id='legacy-v1' FOR UPDATE");
            if (!$state) {
                throw new \LogicException('Сначала примените миграции возвратов.');
            }

            if ($state['completed_at'] !== null) {
                return ['alreadyImported' => true] + json_decode($state['report'], true, flags: JSON_THROW_ON_ERROR);
            }
            foreach (['order_returns', 'order_return_items', 'order_return_drafts', 'order_return_draft_items'] as $table) {
                if ($this->app->fetchOne('SELECT COUNT(*) FROM ' . $table)) {
                    throw new \DomainException('Таблицы возвратов уже содержат данные; автоматический импорт остановлен.');
                }
            }
            $report = [
                'returns' => 0,
                'items' => 0,
                'drafts' => 0,
                'missingOrders' => 0,
                'missingProducts' => 0,
                'orphanItems' => 0,
                'missingAcceptors' => 0,
                'missingProcessors' => 0,
                'inconsistentProcessing' => 0,
                'invalidDraftItems' => 0,
                'invalidDraftIds' => [],
            ];
            $this->legacy->transactional(function () use (&$report): void {
                foreach ($this->legacy->iterateAssociative('SELECT * FROM sklad_order_returns ORDER BY returnID') as $row) {
                    $this->app->insert('order_returns', [
                        'id' => $row['returnID'], 'order_id' => $row['orderID'],
                        'accepted_at' => $row['returnDate'], 'accepted_by' => $row['returnTaken'],
                        'processed_at' => $row['returnMDate'], 'processed_by' => $row['userID'],
                        'refund_amount' => $row['returnAmount'], 'source_payload' => $this->json($row),
                    ]);
                    ++$report['returns'];
                }
                foreach ($this->legacy->iterateAssociative('SELECT * FROM sklad_order_returns_carts ORDER BY orcID') as $row) {
                    $this->app->insert('order_return_items', ['id' => $row['orcID'], 'return_id' => $row['returnID'], 'product_id' => $row['productID'], 'quantity' => $row['productQuantity'], 'price' => $row['productPrice']]);
                    ++$report['items'];
                }
                foreach ($this->legacy->iterateAssociative('SELECT * FROM sklad_order_returnDraft ORDER BY id') as $row) {
                    $items = @unserialize($row['products'], ['allowed_classes' => false]);
                    if (!is_array($items)) {
                        ++$report['invalidDraftItems'];
                        $report['invalidDraftIds'][] = (int) $row['id'];
                        $items = [];
                    }
                    $this->app->insert('order_return_drafts', ['id' => $row['id'], 'user_id' => $row['userID'], 'created_at' => $row['date'], 'comment' => $row['comment'] ?? '', 'done' => $row['done'], 'source_payload' => $this->json($row)]);
                    foreach ($items as $product => $quantity) {
                        if (!ctype_digit((string) $product) || (int) $product <= 0 || !is_scalar($quantity) || !preg_match('/^-?\d{1,12}(?:\.\d{1,3})?$/D', (string) $quantity)) {
                            ++$report['invalidDraftItems'];
                            $report['invalidDraftIds'][] = (int) $row['id'];
                            continue;
                        }
                        $this->app->insert('order_return_draft_items', ['draft_id' => $row['id'], 'product_id' => $product, 'quantity' => (string) $quantity]);
                    }
                    ++$report['drafts'];
                }
            });
            $report['missingOrders'] = (int) $this->app->fetchOne('SELECT COUNT(*) FROM order_returns r LEFT JOIN orders o ON o.id=r.order_id WHERE o.id IS NULL');
            $report['missingAcceptors'] = (int) $this->app->fetchOne('SELECT COUNT(*) FROM order_returns r LEFT JOIN identity_users u ON u.id=r.accepted_by WHERE u.id IS NULL');
            $report['missingProcessors'] = (int) $this->app->fetchOne('SELECT COUNT(*) FROM order_returns r LEFT JOIN identity_users u ON u.id=r.processed_by WHERE r.processed_by IS NOT NULL AND u.id IS NULL');
            $report['orphanItems'] = (int) $this->app->fetchOne('SELECT COUNT(*) FROM order_return_items i LEFT JOIN order_returns r ON r.id=i.return_id WHERE r.id IS NULL');
            $report['missingProducts'] = (int) $this->app->fetchOne('SELECT COUNT(*) FROM order_return_items i LEFT JOIN products p ON p.id=i.product_id WHERE p.id IS NULL');
            $report['inconsistentProcessing'] = (int) $this->app->fetchOne('SELECT COUNT(*) FROM order_returns WHERE (processed_by IS NULL AND processed_at IS NOT NULL) OR (processed_by IS NOT NULL AND processed_at IS NULL)');
            $this->app->update('order_return_imports', ['completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'report' => $this->json($report)], ['id' => 'legacy-v1']);
            return ['alreadyImported' => false] + $report;
        });
    }

    private function json(array $row): string {
        return json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
