<?php

declare(strict_types=1);

namespace App\Domain\Order\Repository;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderListSort;
use App\Domain\Order\ReadModel\Dtos\OrderCardSourceDto;
use App\Domain\Order\ReadModel\Dtos\OrderListItemDto;
use App\Domain\Order\ReadModel\Dtos\OrderSearchDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class OrderViewRepository {
    public function __construct(
        private Connection $connection,
    ) {
    }

    /** @return list<OrderListItemDto> */
    public function search(string $query, array $statuses, array $managers, int $page, int $limit, ?OrderSearchDto $criteria = null, OrderListSort $sort = OrderListSort::Id, string $direction = 'DESC'): array {
        $column = match ($sort) {
            OrderListSort::Id => 'orders.id',
            OrderListSort::CreatedAt => 'orders.created_at',
            OrderListSort::DesiredAt => 'orders.desired_at',
            OrderListSort::Customer => 'orders.customer_name',
            OrderListSort::Status => "CASE orders.status WHEN 'new' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'ready_for_shipment' THEN 3 WHEN 'delivered' THEN 4 WHEN 'partially_shipped' THEN 5 WHEN 'paid_and_delivered' THEN 6 WHEN 'cancelled' THEN 7 END",
        };
        $builder = $this->listQuery($query, $statuses, $managers, $criteria)
            ->select("orders.id", "orders.created_at", "orders.desired_at", "orders.shipping_address", "orders.comments", "orders.status", "orders.customer_name")
            ->orderBy($column, strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        if ($sort !== OrderListSort::Id) {
            $builder->addOrderBy('orders.id', 'DESC');
        }

        return array_map(
            fn (array $row): OrderListItemDto => new OrderListItemDto((int) $row["id"], $row["created_at"], $row["desired_at"], $row["customer_name"], $this->shippingAddress($row), $this->status((string) $row["status"]), $this->status((string) $row["status"])->title(), $this->statusColor($this->status((string) $row["status"]))),
            $builder->executeQuery()->fetchAllAssociative(),
        );
    }

    public function count(string $query, array $statuses, array $managers, ?OrderSearchDto $criteria = null): int {
        return (int) $this->listQuery($query, $statuses, $managers, $criteria)->select('COUNT(*)')->executeQuery()->fetchOne();
    }

    /** @return list<array{id: int|string, name: string}> */
    public function findManagers(): array {
        return $this->connection->fetchAllAssociative("SELECT DISTINCT orders.manager_id AS id, COALESCE(NULLIF(users.display_name, ''), CONCAT('Пользователь #', orders.manager_id)) AS name FROM orders LEFT JOIN identity_users users ON users.id = orders.manager_id WHERE orders.manager_id IS NOT NULL ORDER BY name, id");
    }

    private function listQuery(string $query, array $statuses, array $managers, ?OrderSearchDto $criteria): QueryBuilder {
        $builder = $this->connection->createQueryBuilder()->from('orders');
        if ($statuses === []) {
            $builder->andWhere('1 = 0');
        } else {
            $builder->andWhere('orders.status IN (:statuses)')->setParameter('statuses', $statuses, ArrayParameterType::STRING);
        }
        if ($managers !== []) {
            $builder->andWhere('orders.manager_id IN (:managers)')->setParameter('managers', $managers, ArrayParameterType::INTEGER);
        }
        if (trim($query) !== '') {
            $builder->andWhere('(CAST(orders.id AS CHAR) LIKE :query OR orders.customer_name LIKE :query OR orders.customer_phone LIKE :query)')->setParameter('query', '%'.trim($query).'%');
        }

        if ($criteria !== null) {
            foreach (['orderId' => 'CAST(orders.id AS CHAR)', 'customer' => 'orders.customer_name', 'address' => 'orders.shipping_address'] as $field => $column) {
                if ($criteria->$field !== '') {
                    $builder->andWhere($column.' LIKE :'.$field)->setParameter($field, '%'.$criteria->$field.'%');
                }
            }
            if ($criteria->phone !== '') {
                $builder->andWhere('(orders.customer_phone LIKE :phone OR EXISTS (SELECT 1 FROM customers customer WHERE customer.id = orders.customer_id AND (customer.phone LIKE :phone OR customer.phone_secondary LIKE :phone OR customer.phone_tertiary LIKE :phone)))')
                    ->setParameter('phone', '%'.$criteria->phone.'%');
            }
            $itemConditions = ['item.order_id = orders.id'];
            if ($criteria->productId > 0) {
                $itemConditions[] = 'item.product_id = :productId';
                $builder->setParameter('productId', $criteria->productId);
            } elseif ($criteria->parentId > 0) {
                $itemConditions[] = 'EXISTS (SELECT 1 FROM products product WHERE product.id = item.product_id AND product.parent_id = :parentId)';
                $builder->setParameter('parentId', $criteria->parentId);
            }
            if ($criteria->quantity !== '') {
                $quantity = Decimal::fromString($criteria->quantity, 3);
                $tolerance = Decimal::fromString('5', 3);
                $itemConditions[] = 'item.quantity > :quantityMin AND item.quantity < :quantityMax';
                $builder->setParameter('quantityMin', $quantity->subtract($tolerance)->format())
                    ->setParameter('quantityMax', $quantity->add($tolerance)->format());
            }
            if (count($itemConditions) > 1) {
                $builder->andWhere('EXISTS (SELECT 1 FROM order_items item WHERE '.implode(' AND ', $itemConditions).')');
            }
        }

        return $builder;
    }

    /** @return array<string, mixed>|null */
    public function findForPrint(int $id): ?array {
        $order = $this->connection->fetchAssociative(
            "SELECT orders.id, orders.created_at, orders.desired_at, orders.customer_name, orders.customer_phone,
                orders.shipping_address, orders.shipping_cost, orders.markup, orders.prepayment,
                COALESCE(NULLIF(users.display_name, ''), CONCAT('Пользователь #', orders.manager_id), '') AS manager_name,
                (SELECT COALESCE(SUM(amount), 0) FROM order_payments WHERE order_id = orders.id AND source IN ('shipment','application_shipment')) AS shipment_payment
            FROM orders LEFT JOIN identity_users users ON users.id = orders.manager_id WHERE orders.id = :id",
            ['id' => $id],
        );
        if ($order === false) {
            return null;
        }

        $order['lines'] = $this->connection->fetchAllAssociative(
            'SELECT product_group_name, product_name, unit, quantity AS Quantity, unit_price AS newPrice, unit_price AS currentPrice FROM order_items WHERE order_id = :id ORDER BY position, id',
            ['id' => $id],
        );

        return $order;
    }

    public function findCard(int $id): ?OrderCardSourceDto {
        $order = $this->connection->fetchAssociative(
            "SELECT id AS orderid, created_at AS order_time, desired_at AS desired_date,
                shipping_address, comments, prepayment, shipping_cost AS shippingCost,
                markup, loyalty, loyalty_comment, status, edit_version,
                customer_name AS customerName, customer_phone AS customerPhone
            FROM orders WHERE id = :id",
            ["id" => $id],
        );
        if ($order === false) {
            return null;
        }

        $lines = $this->connection->fetchAllAssociative(
            "SELECT item.position AS orderCartID, item.product_id AS productID, item.quantity AS Quantity, item.unit_price AS newPrice, item.unit_price AS currentPrice, item.product_group_name AS group_name, item.product_name AS name, item.unit AS measure,
                COALESCE(parent.weight_per_unit, 0) AS weight_per_unit
            FROM order_items item LEFT JOIN products product ON product.id = item.product_id LEFT JOIN products parent ON parent.id = product.parent_id
            WHERE item.order_id = :id ORDER BY item.position, item.id",
            ["id" => $id],
        );
        $shipmentQuantities = $this->connection->fetchAllKeyValue("SELECT item.product_id, SUM(item.quantity) FROM order_shipment_items item INNER JOIN order_shipments shipment ON shipment.id = item.shipment_id WHERE shipment.order_id = :id GROUP BY item.product_id", ["id" => $id]);
        $shipmentPayment = (string) $this->connection->fetchOne("SELECT COALESCE(SUM(amount), 0) FROM order_payments WHERE order_id = :id AND source IN ('shipment','application_shipment')", ["id" => $id]);
        $payments = $this->connection->fetchAllAssociative(
            "SELECT paid_at AS paymentDate, amount AS sumOfPayment, comment AS paymentComment, CASE WHEN source IN ('shipment','application_shipment') THEN 'Оплата доставки' ELSE 'Оплата заказа' END AS paymentType FROM order_payments WHERE order_id = :id ORDER BY paid_at DESC, source DESC, source_id DESC",
            ["id" => $id],
        );
        $statusHistory = $this->statusHistory($id);

        return new OrderCardSourceDto($order, $lines, $shipmentQuantities, $shipmentPayment, $payments, $statusHistory);
    }

    public function statusHistory(int $id): array {

        return $this->connection->fetchAllAssociative("
            SELECT history.changed_at AS status_change_time, history.comment AS changelog,
                history.snapshot, history.user_id, COALESCE(users.display_name, '') AS user_name
            FROM (
                SELECT id, changed_at, comment, user_id, NULL AS snapshot, 0 AS source
                FROM order_status_history WHERE order_id = :id
                UNION ALL
                SELECT id, created_at AS changed_at, message AS comment, user_id,
                    CASE WHEN event IN ('created', 'updated') THEN snapshot ELSE NULL END AS snapshot,
                    1 AS source
                FROM order_changelog WHERE order_id = :id
            ) history
            LEFT JOIN identity_users users ON users.id = history.user_id
            ORDER BY history.changed_at DESC, history.source DESC, history.id DESC
        ", ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findForEdit(int $id): ?array {
        $order = $this->connection->fetchAssociative(
            'SELECT id, customer_name, customer_phone, desired_at, shipping_address, shipping_cost, comments, markup, status, edit_version, prepayment FROM orders WHERE id = :id',
            ['id' => $id],
        );
        if ($order === false) {
            return null;
        }
        $order['lines'] = $this->connection->fetchAllAssociative(
            'SELECT item.id, item.product_id, item.product_name, item.product_group_name, item.unit, item.quantity, item.unit_price, item.position, product.parent_id FROM order_items item LEFT JOIN products product ON product.id = item.product_id WHERE item.order_id = :id ORDER BY item.position, item.id',
            ['id' => $id],
        );
        $order['hasPayments'] = Decimal::fromString((string) $order['prepayment'], 2)->scaledValue() > 0
            || $this->connection->fetchOne('SELECT 1 FROM order_payments WHERE order_id = :id AND amount > 0 LIMIT 1', ['id' => $id]) !== false;

        return $order;
    }

    private function shippingAddress(array $row): string {
        $address = trim((string) ($row["shipping_address"] ?? ""));

        return $address !== "" ? $address : (string) ($row["comments"] ?? "");
    }

    private function status(string $status): OrderStatus {
        return OrderStatus::tryFrom($status) ?? OrderStatus::New;
    }

    private function statusColor(OrderStatus $status): string {
        return match ($status) {
            OrderStatus::New => "color: #008000; font-weight: bold;",
            OrderStatus::InProgress => "color: #000000;",
            OrderStatus::ReadyForShipment => "color: #008000; font-weight: bold;",
            OrderStatus::Delivered => "color: #ff7f27;",
            OrderStatus::PartiallyShipped => "color: #6600ff; font-style: italic;",
            OrderStatus::PaidAndDelivered => "color: #ff7f27;",
            OrderStatus::Cancelled => "color: #cc0000;",
        };
    }
}
