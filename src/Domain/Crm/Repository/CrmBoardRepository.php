<?php declare(strict_types=1);

namespace App\Domain\Crm\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class CrmBoardRepository {
    public function __construct(private Connection $connection) {}

    public function deals(string $state, string $query, array $managers): array {
        $where = 'd.state=:state';
        $args = ['state'=>$state];
        $types = [];
        if ($query !== '') {
            $where .= ' AND (c.name LIKE :q OR d.comment LIKE :q OR CAST(d.id AS CHAR)=:id)';
            $args += ['q'=>'%'.$query.'%', 'id'=>$query];
        }
        if ($managers !== []) {
            $where .= ' AND ct.executor_id IN (:managers)';
            $args['managers'] = $managers;
            $types['managers'] = ArrayParameterType::INTEGER;
        }

        return $this->connection->fetchAllAssociative('SELECT d.*,c.name customer_name,c.phone,ct.due_at,ct.comment contact_comment,cu.display_name executor_name,
            (SELECT COUNT(*) FROM crm_deal_history h WHERE h.deal_id=d.id) steps
            FROM crm_deals d LEFT JOIN customers c ON c.id=d.customer_id
            LEFT JOIN crm_deal_contacts ct ON ct.id=(SELECT MAX(x.id) FROM crm_deal_contacts x WHERE x.deal_id=d.id AND x.completed_at IS NULL)
            LEFT JOIN identity_users cu ON cu.id=ct.executor_id
            WHERE '.$where.' ORDER BY ct.due_at IS NULL,ct.due_at,d.created_at DESC,d.id DESC', $args, $types);
    }

    public function orders(string $query, array $managers): array {
        [$filter, $args, $types] = $this->orderFilter($query, $managers);

        return $this->connection->fetchAllAssociative("SELECT o.*,u.display_name executor_name,
            (SELECT COUNT(*) FROM order_status_history h WHERE h.order_id=o.id) + (SELECT COUNT(*) FROM order_changelog h WHERE h.order_id=o.id) steps
            FROM orders o LEFT JOIN identity_users u ON u.id=o.manager_id
            WHERE o.status IN ('new','in_progress','ready_for_shipment','delivered','partially_shipped')
            AND (NOT EXISTS(SELECT 1 FROM order_items i WHERE i.order_id=o.id) OR EXISTS(
                SELECT 1 FROM order_items i WHERE i.order_id=o.id
                GROUP BY i.product_id HAVING SUM(i.quantity) > (
                    SELECT COALESCE(SUM(si.quantity),0) FROM order_shipment_items si INNER JOIN order_shipments s ON s.id=si.shipment_id
                    WHERE s.order_id=o.id AND si.product_id=i.product_id)))".$filter.' ORDER BY o.desired_at IS NULL,o.desired_at,o.id', $args, $types);
    }

    public function shipments(string $query, array $managers): array {
        [$filter, $args, $types] = $this->orderFilter($query, $managers, true);

        return $this->connection->fetchAllAssociative('SELECT t.*,o.customer_name,o.markup,o.manager_id,
            (SELECT COUNT(*) FROM order_status_history h WHERE h.task_id = t.id)
                + (SELECT COUNT(*) FROM order_changelog h
                    WHERE h.order_id = t.order_id
                    AND CAST(JSON_UNQUOTE(JSON_EXTRACT(h.snapshot, \'$.context.taskId\')) AS UNSIGNED) = t.id) AS steps
            FROM warehouse_shipping_tasks t LEFT JOIN orders o ON o.id=t.order_id
            WHERE t.type=2 AND t.status=0'.$filter.' ORDER BY t.performance_date IS NULL,t.performance_date,t.id', $args, $types);
    }

    public function orderLines(array $ids): array {
        if ($ids === []) {

            return [];
        }

        return $this->connection->fetchAllAssociative('SELECT order_id,product_id,quantity,unit_price FROM order_items WHERE order_id IN (?) ORDER BY position,id', [$ids], [ArrayParameterType::INTEGER]);
    }

    public function shipmentLines(array $ids): array {
        if ($ids === []) {

            return [];
        }

        return $this->connection->fetchAllAssociative("SELECT i.*,CONCAT_WS(' ',g.name,p.name) product_name,p.unit FROM warehouse_shipping_items i LEFT JOIN products p ON p.id=i.product_id LEFT JOIN products g ON g.id=p.parent_id WHERE i.task_id IN (?) ORDER BY i.id", [$ids], [ArrayParameterType::INTEGER]);
    }

    private function orderFilter(string $query, array $managers, bool $shipment = false): array {
        $filter = '';
        $args = [];
        $types = [];
        if ($query !== '') {
            $filter .= ' AND (o.customer_name LIKE :q OR o.customer_phone LIKE :q OR o.comments LIKE :q OR CAST(o.id AS CHAR)=:id'.($shipment ? ' OR t.comment LIKE :q OR CAST(t.id AS CHAR)=:id' : '').')';
            $args = ['q'=>'%'.$query.'%', 'id'=>$query];
        }
        if ($managers !== []) {
            $filter .= ' AND o.manager_id IN (:managers)';
            $args['managers'] = $managers;
            $types['managers'] = ArrayParameterType::INTEGER;
        }

        return [$filter, $args, $types];
    }
}
