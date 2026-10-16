<?php declare(strict_types=1);

namespace App\Domain\Order\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OrderReturnRepository {
    public function __construct(private Connection $connection) {}

    public function requireImported(): void {
        if (!$this->connection->fetchOne("SELECT completed_at FROM order_return_imports WHERE id='legacy-v1'")) {
            throw new \DomainException('Сначала выполните legacy:import-order-returns.');
        }
    }

    public function operation(string $uuid, bool $draft): ?array {
        return $this->connection->fetchAssociative('SELECT * FROM ' . ($draft ? 'order_return_drafts' : 'order_returns') . ' WHERE operation_id=?', [$uuid]) ?: null;
    }

    public function lockActor(int $id): void {
        if (!$this->connection->fetchOne('SELECT id FROM identity_users WHERE id=? FOR UPDATE', [$id])) throw new \DomainException('Пользователь не найден.');
    }

    public function draft(int $id, bool $lock = false): array {
        $row = $this->connection->fetchAssociative('SELECT * FROM order_return_drafts WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$row) throw new NotFoundHttpException('Заявка не найдена.');
        $row['items'] = $this->connection->fetchAllAssociative('SELECT * FROM order_return_draft_items WHERE draft_id=? ORDER BY id', [$id]);
        return $row;
    }

    public function createDraft(array $fields, array $items): int {
        try {
            $this->connection->insert('order_return_drafts', $fields);
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('Идентификатор операции уже использован.', $exception);
        }
        $id = (int) $this->connection->lastInsertId();
        foreach ($items as $product => $quantity) {
            $this->connection->insert('order_return_draft_items', ['draft_id' => $id, 'product_id' => $product, 'quantity' => $quantity]);
        }
        return $id;
    }

    public function create(array $fields, array $items): int {
        try {
            $this->connection->insert('order_returns', $fields);
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('Заявка или идентификатор операции уже использованы.', $exception);
        }
        $id = (int) $this->connection->lastInsertId();
        foreach ($items as $item) $this->connection->insert('order_return_items', ['return_id' => $id] + $item);
        if ($fields['draft_id'] !== null) $this->connection->update('order_return_drafts', ['done' => 1], ['id' => $fields['draft_id']]);
        return $id;
    }

    public function order(int $id): array {
        $order = $this->connection->fetchAssociative('SELECT id,status,edit_version,customer_name FROM orders WHERE id=?', [$id]);
        if (!$order) throw new NotFoundHttpException('Заказ не найден.');
        $order['items'] = $this->connection->fetchAllAssociative('SELECT product_id,product_name,product_group_name,unit,quantity,unit_price FROM order_items WHERE order_id=? ORDER BY position,id', [$id]);
        return $order;
    }

    public function shipped(int $orderId): array {
        return $this->connection->fetchAllKeyValue('SELECT i.product_id,SUM(i.quantity) FROM order_shipment_items i INNER JOIN order_shipments s ON s.id=i.shipment_id WHERE s.order_id=? GROUP BY i.product_id', [$orderId]);
    }

    public function pending(int $orderId): array {
        return $this->connection->fetchAllKeyValue('SELECT i.product_id,SUM(i.quantity) FROM order_return_items i INNER JOIN order_returns r ON r.id=i.return_id WHERE r.order_id=? AND r.processed_by IS NULL GROUP BY i.product_id', [$orderId]);
    }

    public function products(): array {
        return $this->connection->fetchAllAssociative("SELECT p.id,CONCAT(COALESCE(g.name,''),' ',p.name) AS name,p.unit FROM products p INNER JOIN products g ON g.id=p.parent_id ORDER BY g.sorting,g.id,p.sorting,p.id");
    }

    public function drafts(bool $archive, int $page): array {
        $rows = $this->connection->fetchAllAssociative("SELECT d.*,COALESCE(u.display_name,CONCAT('Пользователь #',d.user_id)) AS author FROM order_return_drafts d LEFT JOIN identity_users u ON u.id=d.user_id WHERE d.done=? ORDER BY d.created_at DESC,d.id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), [$archive ? 1 : 0]);
        $items = $this->listItems(array_column($rows, 'id'), true);
        $report = $this->connection->fetchOne("SELECT report FROM order_return_imports WHERE id='legacy-v1'");
        $invalid = $report ? (json_decode($report, true, flags: JSON_THROW_ON_ERROR)['invalidDraftIds'] ?? []) : [];
        foreach ($rows as &$row) {
            $row['items'] = $items[$row['id']] ?? [];
            $row['importWarning'] = in_array((int) $row['id'], $invalid, true);
        }
        return $rows;
    }

    public function returns(?int $orderId, int $page = 1): array {
        $rows = $this->connection->fetchAllAssociative("SELECT r.*,COALESCE(u.display_name,CONCAT('Пользователь #',r.accepted_by)) AS author,COALESCE(p.display_name,CONCAT('Пользователь #',r.processed_by)) AS processor FROM order_returns r LEFT JOIN identity_users u ON u.id=r.accepted_by LEFT JOIN identity_users p ON p.id=r.processed_by WHERE " . ($orderId === null ? 'r.processed_by IS NULL' : 'r.order_id=?') . ' ORDER BY r.id DESC' . ($orderId === null ? ' LIMIT 50 OFFSET ' . (($page - 1) * 50) : ''), $orderId === null ? [] : [$orderId]);
        $items = $this->listItems(array_column($rows, 'id'), false);
        foreach ($rows as &$row) {
            $row['items'] = $items[$row['id']] ?? [];
        }
        return $rows;
    }

    private function listItems(array $ids, bool $draft): array {
        if ($ids === []) return [];
        $column = $draft ? 'draft_id' : 'return_id';
        $table = $draft ? 'order_return_draft_items' : 'order_return_items';
        $rows = $this->connection->fetchAllAssociative("SELECT i.*,CONCAT(COALESCE(g.name,''),' ',COALESCE(p.name,CONCAT('Товар #',i.product_id))) AS name FROM " . $table . ' i LEFT JOIN products p ON p.id=i.product_id LEFT JOIN products g ON g.id=p.parent_id WHERE i.' . $column . ' IN (:ids) ORDER BY i.id', ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
        $result = [];
        foreach ($rows as $row) $result[$row[$column]][] = $row;
        return $result;
    }

    public function draftCount(bool $archive): int {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM order_return_drafts WHERE done=?', [$archive ? 1 : 0]);
    }

    public function pendingCount(): int {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM order_returns WHERE processed_by IS NULL');
    }
}
