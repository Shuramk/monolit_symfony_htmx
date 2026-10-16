<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShippingRepository {
    public function __construct(private Connection $connection) {}

    public function repairHistoryTimezone(bool $apply = false): int {

        return $this->connection->transactional(function () use ($apply): int {
            $entries = $this->connection->fetchAllAssociative('
                SELECT id, order_id, event, created_at, snapshot
                FROM order_changelog
                WHERE event IN (:events)
                    AND JSON_EXTRACT(snapshot, \'$.context.timezone\') IS NULL
                ORDER BY id
            ' . ($apply ? ' FOR UPDATE' : ''), [
                'events' => ['shipping_created', 'shipping_edited', 'shipping_collect', 'shipping_complete', 'shipping_delete'],
            ], ['events' => ArrayParameterType::STRING]);
            if (!$apply) {

                return count($entries);
            }
            $utc = new \DateTimeZone('UTC');
            $kyiv = new \DateTimeZone('Europe/Kyiv');
            foreach ($entries as $entry) {
                $snapshot = json_decode($entry['snapshot'], true, flags: JSON_THROW_ON_ERROR);
                $date = (new \DateTimeImmutable($entry['created_at'], $utc))->setTimezone($kyiv)->format('Y-m-d H:i:s');
                $snapshot['context']['timezone'] = 'Europe/Kyiv';
                $snapshot['context']['originalUtcCreatedAt'] = $entry['created_at'];
                $this->connection->update('order_changelog', [
                    'created_at' => $date,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ], ['id' => $entry['id']]);
                $field = match ($entry['event']) {
                    'shipping_created' => 'created_at',
                    'shipping_complete' => 'completed_at',
                    default => null,
                };
                if ($field !== null && isset($snapshot['context']['taskId'])) {
                    $this->connection->update('warehouse_shipping_tasks', [$field => $date], [
                        'id' => $snapshot['context']['taskId'],
                        'order_id' => $entry['order_id'],
                        $field => $entry['created_at'],
                    ]);
                }
            }

            return count($entries);
        });
    }

    public function task(int $id, bool $lock = false): array {
        $row = $this->connection->fetchAssociative('
            SELECT * FROM warehouse_shipping_tasks WHERE id = :id AND type = 2
        ' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);
        if ($row === false) {
            throw new NotFoundHttpException('Задача на отгрузку не найдена.');
        }
        $row['payload'] = json_decode($row['payload'] ?? '{}', true, flags: JSON_THROW_ON_ERROR) ?? [];
        $row['lines'] = $this->lines([$id]);

        return $row;
    }

    public function operation(string $key): ?array {
        $id = $this->connection->fetchOne('
            SELECT id FROM warehouse_shipping_tasks WHERE operation_key = :key
        ', ['key' => $key]);

        return $id === false ? null : $this->task((int) $id);
    }

    public function create(int $orderId, string $date, string $comment, array $payload, string $key, string $now): int {
        $this->connection->insert('warehouse_shipping_tasks', [
            'order_id' => $orderId, 'type' => 2, 'status' => 0,
            'performance_date' => $date, 'created_at' => $now, 'comment' => $comment,
            'operation_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(array $task, array $fields, array $payload): void {
        $fields['version'] = (int) $task['version'] + 1;
        $fields['payload'] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->connection->update('warehouse_shipping_tasks', $fields, ['id' => $task['id']]);
    }

    public function replaceLines(int $id, array $lines): void {
        $this->connection->delete('warehouse_shipping_items', ['task_id' => $id]);
        foreach ($lines as $line) {
            $this->connection->insert('warehouse_shipping_items', ['task_id' => $id] + $line);
        }
    }

    public function updateLinePrice(int $taskId, int $productId, string $price): void {
        $this->connection->update('warehouse_shipping_items', ['price' => $price], [
            'task_id' => $taskId, 'product_id' => $productId,
        ]);
    }

    public function orderSource(int $id): array {
        $order = $this->connection->fetchAssociative('
            SELECT id, customer_name, customer_phone, shipping_address, desired_at,
                status, edit_version, markup, prepayment, shipping_cost
            FROM orders WHERE id = :id
        ', ['id' => $id]);
        if ($order === false) {
            throw new NotFoundHttpException('Заказ не найден.');
        }
        $order['lines'] = $this->connection->fetchAllAssociative('
            SELECT item.product_id, MAX(item.product_name) AS name, MAX(item.product_group_name) AS group_name,
                MAX(item.unit) AS unit, SUM(item.quantity) AS quantity,
                COALESCE(
                    SUM(item.quantity * item.unit_price) / NULLIF(SUM(item.quantity), 0),
                    MAX(item.unit_price)
                ) AS price,
                MAX(p.storage_office) AS office, MAX(p.storage_workshop) AS workshop,
                MAX(parent.weight_per_unit) AS weight_per_unit
            FROM order_items item
            LEFT JOIN products p ON p.id = item.product_id
            LEFT JOIN products parent ON parent.id = p.parent_id
            WHERE item.order_id = :id
            GROUP BY item.product_id ORDER BY MIN(item.position), MIN(item.id)
        ', ['id' => $id]);
        $order['shipped'] = $this->connection->fetchAllKeyValue('
            SELECT item.product_id, SUM(item.quantity)
            FROM order_shipment_items item
            INNER JOIN order_shipments shipment ON shipment.id = item.shipment_id
            WHERE shipment.order_id = :id GROUP BY item.product_id
        ', ['id' => $id]);
        $order['deliveryPaid'] = $this->connection->fetchOne("
            SELECT COALESCE(SUM(amount), 0) FROM order_payments
            WHERE order_id = :id AND source IN ('shipment', 'application_shipment')
        ", ['id' => $id]);

        return $order;
    }

    public function planned(int $orderId, int $except = 0): array {

        return $this->connection->fetchAllKeyValue('
            SELECT item.product_id, SUM(item.quantity)
            FROM warehouse_shipping_items item
            INNER JOIN warehouse_shipping_tasks task ON task.id = item.task_id
            WHERE task.order_id = :orderId AND task.type = 2 AND task.status = 0 AND task.id <> :except
            GROUP BY item.product_id
        ', ['orderId' => $orderId, 'except' => $except]);
    }

    public function reservationsForProducts(array $ids): array {
        if ($ids === []) {

            return [];
        }

        return $this->connection->fetchAllAssociative("
            SELECT item.product_id, task.id AS task_id, task.order_id, task.performance_date,
                SUM(item.quantity) AS quantity,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(task.payload, '$.storage')), '2') AS storage
            FROM warehouse_shipping_items item
            INNER JOIN warehouse_shipping_tasks task ON task.id = item.task_id
            WHERE item.product_id IN (:ids) AND task.type = 2 AND task.status = 0
            GROUP BY item.product_id, task.id, task.order_id, task.performance_date, task.payload
            ORDER BY task.performance_date, task.id
        ", ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
    }

    public function carriers(): array {

        return $this->connection->fetchAllAssociative('SELECT id, name, phone FROM shipping_carriers ORDER BY priority, id');
    }

    public function invoiceNumber(int $orderId, int $taskId): int {

        return (int) $this->connection->fetchOne('
            SELECT COUNT(*) FROM warehouse_shipping_tasks
            WHERE order_id = :orderId AND type = 2 AND status <> 2 AND id <= :taskId
        ', ['orderId' => $orderId, 'taskId' => $taskId]);
    }

    public function approvalHistory(int $orderId, int $taskId, string $status, bool $allowed, int $actor, \DateTimeImmutable $now): void {
        $this->connection->insert('order_status_history', [
            'order_id' => $orderId, 'task_id' => $taskId, 'status' => $status,
            'changed_at' => $now->format('Y-m-d H:i:s'), 'user_id' => $actor,
            'imported_at' => $now->format('Y-m-d H:i:s'),
            'comment' => ($allowed ? 'Согласована' : 'Снято согласование:') . ' задача на отгрузку №' . $taskId,
        ]);
    }

    public function requireCarrier(int $id): void {
        if ($id > 0 && $this->connection->fetchOne('SELECT 1 FROM shipping_carriers WHERE id = :id', ['id' => $id]) === false) {
            throw new \DomainException('Перевозчик не найден.');
        }
    }

    public function recordShipment(int $orderId, int $taskId, ?int $carrierId, array $lines, string $now): int {
        $this->connection->executeStatement('INSERT INTO shipping_shipment_numbers () VALUES ()');
        $id = (int) $this->connection->lastInsertId();
        $this->connection->insert('order_shipments', [
            'id' => $id, 'order_id' => $orderId, 'task_id' => $taskId,
            'carrier_id' => $carrierId, 'imported_at' => $now,
        ]);
        foreach ($lines as $line) {
            $this->connection->insert('order_shipment_items', [
                'shipment_id' => $id, 'product_id' => $line['product_id'], 'quantity' => $line['quantity'],
            ]);
        }

        return $id;
    }

    public function page(bool $archive, string $query, int $page, bool $workshopOnly = false): array {
        $where = 'task.type = 2 AND task.status = :status';
        $args = ['status' => $archive ? 1 : 0];
        if ($workshopOnly) {
            $where .= " AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(task.payload, '$.storage')), '2') = '2'";
        }
        if (trim($query) !== '') {
            $where .= ' AND (CAST(task.id AS CHAR) = :id OR CAST(task.order_id AS CHAR) = :id OR task.comment LIKE :q OR orders.customer_name LIKE :q)';
            $args += ['id' => trim($query), 'q' => '%' . trim($query) . '%'];
        }
        $from = ' FROM warehouse_shipping_tasks task LEFT JOIN orders ON orders.id = task.order_id WHERE ' . $where;
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)' . $from, $args);
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($page, $pages));
        $sort = $archive ? 'task.completed_at DESC, task.id DESC' : 'task.performance_date, task.id';
        $rows = $this->connection->fetchAllAssociative('
            SELECT task.*, orders.customer_name, orders.shipping_address
        ' . $from . ' ORDER BY ' . $sort . ' LIMIT 50 OFFSET ' . (($page - 1) * 50), $args);

        return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public function lines(array $ids): array {
        if ($ids === []) {

            return [];
        }

        return $this->connection->fetchAllAssociative('
            SELECT item.*, CONCAT_WS(\' \', parent.name, product.name) AS name, product.unit, parent.pallets
            FROM warehouse_shipping_items item
            LEFT JOIN products product ON product.id = item.product_id
            LEFT JOIN products parent ON parent.id = product.parent_id
            WHERE item.task_id IN (:ids) ORDER BY item.task_id, item.id
        ', ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
    }
}
