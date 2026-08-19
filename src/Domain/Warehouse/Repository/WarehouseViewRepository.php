<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;

final readonly class WarehouseViewRepository {
    public function __construct(private Connection $connection) {}

    public function requireImported(): void {
        if (!$this->connection->fetchOne("SELECT completed_at FROM warehouse_imports WHERE id = 'legacy-v1'")) throw new \DomainException('Сначала перенесите данные склада командой legacy:import-warehouse.');
    }

    public function overview(): array {
        return $this->connection->fetchAllAssociative('SELECT parent.id, parent.name, parent.unit, parent.type, parent.forms, parent.pallets, COALESCE(stock.office, 0) AS office, COALESCE(stock.workshop, 0) AS workshop FROM products parent LEFT JOIN (SELECT parent_id, SUM(storage_office) AS office, SUM(storage_workshop) AS workshop FROM products WHERE parent_id IS NOT NULL GROUP BY parent_id) stock ON stock.parent_id = parent.id WHERE parent.parent_id IS NULL AND parent.enabled = 1 ORDER BY parent.sorting, parent.id');
    }

    public function findGroup(int $id): ?array {
        return $this->connection->fetchAssociative('SELECT id, name, unit FROM products WHERE id = ? AND parent_id IS NULL AND enabled = 1', [$id]) ?: null;
    }

    public function inventoryVariants(int $type): array {
        return $this->connection->fetchAllAssociative('SELECT variant.id, variant.parent_id, variant.name FROM products variant INNER JOIN products parent ON parent.id = variant.parent_id WHERE parent.parent_id IS NULL AND parent.enabled = 1 AND parent.type = ? ORDER BY parent.sorting, parent.id, variant.sorting, variant.id', [$type]);
    }

    public function groups(): array {
        return $this->connection->fetchAllAssociative('SELECT id, name FROM products WHERE parent_id IS NULL AND enabled = 1 ORDER BY sorting, id');
    }

    public function count(string $query, int $groupId): int {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM products p INNER JOIN products parent ON parent.id = p.parent_id WHERE (:groupId > 0 OR p.enabled = 1) AND parent.enabled = 1 AND (:groupId = 0 OR parent.id = :groupId) AND (:query = '' OR p.name LIKE :search OR parent.name LIKE :search)", $this->parameters($query, $groupId));
    }

    public function rows(string $query, int $groupId, int $limit, int $offset): array {
        // Aggregate each side before joining: duplicate order lines and multiple shipments must not multiply reserves.
        return $this->connection->fetchAllAssociative("SELECT p.id, parent.name AS group_name, p.name, p.unit, p.price, p.min_quantity, p.edit_version, p.storage_office, p.storage_workshop, p.storage_third, COALESCE(reserve.quantity, 0) AS reserved,
                COALESCE(tasks.quantity,0) AS task_quantity, COALESCE(cover.quantity,0) AS cover_quantity
            FROM products p INNER JOIN products parent ON parent.id = p.parent_id
            LEFT JOIN (SELECT item.product_id, SUM(item.quantity) AS quantity FROM warehouse_shipping_items item INNER JOIN warehouse_shipping_tasks task ON task.id = item.task_id WHERE task.type = 2 AND task.status = 0 GROUP BY item.product_id) tasks ON tasks.product_id = p.id
            LEFT JOIN (SELECT product_id, SUM(quantity) AS quantity FROM production_entries WHERE quantity IS NOT NULL AND accepted_by IS NULL GROUP BY product_id) cover ON cover.product_id = p.id
            LEFT JOIN (
                SELECT ordered.product_id, SUM(ordered.quantity - COALESCE(shipped.quantity, 0)) AS quantity
                FROM (SELECT item.order_id, item.product_id, SUM(item.quantity) AS quantity FROM order_items item INNER JOIN orders o ON o.id = item.order_id WHERE o.status IN ('new','in_progress','ready_for_shipment','partially_shipped') GROUP BY item.order_id, item.product_id) ordered
                LEFT JOIN (SELECT shipment.order_id, item.product_id, SUM(item.quantity) AS quantity FROM order_shipment_items item INNER JOIN order_shipments shipment ON shipment.id = item.shipment_id GROUP BY shipment.order_id, item.product_id) shipped ON shipped.order_id = ordered.order_id AND shipped.product_id = ordered.product_id
                WHERE ROUND(ordered.quantity - COALESCE(shipped.quantity, 0), 2) > 0 GROUP BY ordered.product_id
            ) reserve ON reserve.product_id = p.id
            WHERE (:groupId > 0 OR p.enabled = 1) AND parent.enabled = 1 AND (:groupId = 0 OR parent.id = :groupId) AND (:query = '' OR p.name LIKE :search OR parent.name LIKE :search)
            ORDER BY parent.sorting, parent.id, p.sorting, p.id LIMIT ".max(1, min(2000, $limit)).' OFFSET '.max(0, $offset), $this->parameters($query, $groupId));
    }

    public function orderDetails(int $groupId): array {
        return $this->remainingOrders('p.parent_id = :groupId', ['groupId'=>$groupId]);
    }

    /** @param list<int> $ids @return array<int, array<string, mixed>> */
    public function stockForProducts(array $ids): array {
        $ids = array_values(array_unique($ids));
        if ($ids === []) return [];
        $rows = $this->connection->fetchAllAssociative("SELECT p.id, p.storage_workshop, COALESCE(tasks.quantity,0) AS task_quantity
            FROM products p LEFT JOIN (
                SELECT item.product_id,SUM(item.quantity) AS quantity FROM warehouse_shipping_items item
                INNER JOIN warehouse_shipping_tasks task ON task.id = item.task_id
                WHERE item.product_id IN (:ids) AND task.type = 2 AND task.status = 0 GROUP BY item.product_id
            ) tasks ON tasks.product_id = p.id WHERE p.id IN (:ids)", ['ids'=>$ids], ['ids'=>ArrayParameterType::INTEGER]);
        $result = [];
        foreach ($rows as $row) $result[(int)$row['id']] = $row + ['orders'=>[]];
        foreach ($this->remainingOrders('p.id IN (:ids)', ['ids'=>$ids], ['ids'=>ArrayParameterType::INTEGER]) as $row) {
            $result[(int)$row['product_id']]['orders'][] = $row;
        }
        return $result;
    }

    /** The condition is supplied only by the repository's two fixed queries above. */
    private function remainingOrders(string $condition, array $params, array $types = []): array {
        return $this->connection->fetchAllAssociative("SELECT ordered.product_id, o.id AS order_id, o.desired_at AS delivery_date, ordered.quantity - COALESCE(shipped.quantity,0) AS quantity
            FROM (SELECT item.order_id,item.product_id,SUM(item.quantity) AS quantity FROM order_items item INNER JOIN products p ON p.id = item.product_id WHERE ".$condition." GROUP BY item.order_id,item.product_id) ordered
            INNER JOIN orders o ON o.id = ordered.order_id
            LEFT JOIN (SELECT shipment.order_id,item.product_id,SUM(item.quantity) AS quantity FROM order_shipment_items item INNER JOIN order_shipments shipment ON shipment.id = item.shipment_id GROUP BY shipment.order_id,item.product_id) shipped ON shipped.order_id = ordered.order_id AND shipped.product_id = ordered.product_id
            WHERE o.status IN ('new','in_progress','ready_for_shipment','partially_shipped') AND ROUND(ordered.quantity - COALESCE(shipped.quantity,0),2) > 0 ORDER BY o.desired_at,o.id", $params, $types);
    }

    public function shippingDetails(int $groupId): array {
        return $this->connection->fetchAllAssociative('SELECT item.product_id, task.id, task.order_id, task.performance_date, SUM(item.quantity) AS quantity FROM warehouse_shipping_items item INNER JOIN warehouse_shipping_tasks task ON task.id = item.task_id INNER JOIN products p ON p.id = item.product_id WHERE p.parent_id = ? AND task.type = 2 AND task.status = 0 GROUP BY item.product_id,task.id,task.order_id,task.performance_date ORDER BY task.performance_date,task.id', [$groupId]);
    }

    public function product(int $id): ?array {
        return $this->connection->fetchAssociative('SELECT p.id,p.name,p.parent_id,p.unit,parent.name AS group_name FROM products p INNER JOIN products parent ON parent.id = p.parent_id WHERE p.id = ? AND parent.enabled = 1', [$id]) ?: null;
    }

    public function history(int $id, int $page): array {
        $total = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM warehouse_history WHERE product_id = ?', [$id]);
        $pages = max(1,(int)ceil($total/50));
        $page = max(1,min($page,$pages));
        $rows = $this->connection->fetchAllAssociative('SELECT h.*, u.display_name AS user_name, shift.display_name AS shift_name FROM warehouse_history h LEFT JOIN identity_users u ON u.id = h.user_id LEFT JOIN identity_users shift ON shift.id = h.shift_id WHERE h.product_id = ? ORDER BY h.created_at DESC,h.id DESC LIMIT 50 OFFSET '.(($page-1)*50), [$id]);
        return ['rows'=>$rows,'page'=>$page,'pages'=>$pages,'total'=>$total];
    }

    public function production(int $groupId): array {
        return $this->connection->fetchAllAssociative('SELECT task.*,p.name,p.unit,COALESCE(done.quantity,0) AS completed FROM production_tasks task INNER JOIN products p ON p.id = task.product_id LEFT JOIN (SELECT task_id,SUM(quantity) AS quantity FROM production_entries GROUP BY task_id) done ON done.task_id = task.id WHERE p.parent_id = ? AND task.done = 0 ORDER BY task.sorting,task.id', [$groupId]);
    }

    public function cover(int $groupId): array {
        return $this->connection->fetchAllAssociative('SELECT entry.*,p.name,p.unit FROM production_entries entry INNER JOIN products p ON p.id = entry.product_id WHERE p.parent_id = ? AND entry.quantity IS NOT NULL AND entry.accepted_by IS NULL ORDER BY entry.created_at,entry.id', [$groupId]);
    }

    private function parameters(string $query, int $groupId): array {
        return ['groupId' => $groupId, 'query' => $query, 'search' => '%'.$query.'%'];
    }
}
