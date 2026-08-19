<?php declare(strict_types=1);
namespace App\Domain\Crm\Repository;

use App\Domain\Crm\ReadModel\CrmHistoryText;
use App\Domain\Crm\ReadModel\Dtos\TaskFilterDto;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CrmRepository {
    public function __construct(private Connection $connection, private CrmHistoryText $historyText) {}
    public function transactional(callable $operation): mixed { return $this->connection->transactional($operation); }
    public function checkVersion(array $row, ?int $version): void { if ($version === null || $version !== (int)$row['version']) throw new ConflictHttpException('Запись уже изменена. Обновите страницу.'); }
    public function deal(int $id, bool $lock = false): array {
        $row = $this->connection->fetchAssociative('SELECT d.*,c.name customer_name,c.phone,u.display_name author_name,s.name stacker_name FROM crm_deals d LEFT JOIN customers c ON c.id=d.customer_id LEFT JOIN identity_users u ON u.id=d.author_id LEFT JOIN customers s ON s.id=d.stacker_id WHERE d.id=?'.($lock ? ' FOR UPDATE' : ''),[$id]);
        if (!$row) throw new NotFoundHttpException('Сделка не найдена.');
        $row['lines'] = $this->connection->fetchAllAssociative("SELECT i.*,CONCAT_WS(' ',p.name,v.name) product_name,v.unit,v.parent_id group_id,p.name group_name,v.name variant_name,v.price catalog_price,CONCAT_WS(' ',COALESCE(NULLIF(p.name_uk,''),p.name),COALESCE(NULLIF(v.name_uk,''),v.name)) product_name_uk FROM crm_deal_items i LEFT JOIN products v ON v.id=i.product_id LEFT JOIN products p ON p.id=v.parent_id WHERE i.deal_id=? ORDER BY i.id",[$id]);
        $row['contact'] = $this->connection->fetchAssociative('SELECT * FROM crm_deal_contacts WHERE deal_id=? AND completed_at IS NULL ORDER BY id DESC LIMIT 1',[$id]) ?: null;
        return $row;
    }
    public function task(int $id, bool $lock = false): array {
        $row = $this->connection->fetchAssociative('SELECT t.*,u.display_name author_name,e.display_name executor_name FROM crm_tasks t LEFT JOIN identity_users u ON u.id=t.author_id LEFT JOIN identity_users e ON e.id=t.executor_id WHERE t.id=?'.($lock ? ' FOR UPDATE' : ''),[$id]);
        if (!$row) throw new NotFoundHttpException('Задача не найдена.');
        $row['coexecutors'] = []; $row['observers'] = [];
        foreach ($this->connection->fetchAllAssociative('SELECT user_id,kind FROM crm_task_participants WHERE task_id=?',[$id]) as $p) $row[$p['kind'] === 'observer' ? 'observers' : 'coexecutors'][] = (int)$p['user_id'];
        return $row;
    }
    public function createDeal(array $data): int { $this->connection->insert('crm_deals',$data); return (int)$this->connection->lastInsertId(); }
    public function createTask(array $data): int { $this->connection->insert('crm_tasks',$data); return (int)$this->connection->lastInsertId(); }
    public function updateDeal(int $id, array $data, int $version): void { $this->update('crm_deals',$id,$data,$version); }
    public function updateTask(int $id, array $data, int $version): void { $this->update('crm_tasks',$id,$data,$version); }
    private function update(string $table, int $id, array $data, int $version): void {
        $data['version'] = $version+1;
        if ($this->connection->update($table,$data,['id'=>$id,'version'=>$version]) !== 1) throw new ConflictHttpException('Запись уже изменена. Обновите страницу.');
    }
    public function replaceItems(int $id, array $lines): void {
        $this->connection->delete('crm_deal_items',['deal_id'=>$id]);
        foreach ($lines as $line) $this->connection->insert('crm_deal_items',['deal_id'=>$id]+$line);
    }
    public function contact(int $dealId, int $actor, ?int $executor, string $due, string $comment, string $now): void {
        $this->connection->executeStatement('UPDATE crm_deal_contacts SET completed_at=? WHERE deal_id=? AND completed_at IS NULL',[$now,$dealId]);
        $this->connection->insert('crm_deal_contacts',['deal_id'=>$dealId,'author_id'=>$actor,'executor_id'=>$executor,'due_at'=>$due,'comment'=>$comment]);
    }
    public function participants(int $id, array $coexecutors, array $observers): void {
        $this->connection->delete('crm_task_participants',['task_id'=>$id]);
        foreach (['coexecutor'=>$coexecutors,'observer'=>$observers] as $kind=>$ids) foreach ($ids as $userId) $this->connection->insert('crm_task_participants',['task_id'=>$id,'user_id'=>$userId,'kind'=>$kind]);
    }
    public function notify(int $task, array $users, int $actor): void {
        foreach (array_unique($users) as $user) if ($user > 0 && $user !== $actor) $this->connection->executeStatement('INSERT IGNORE INTO crm_task_notifications(task_id,user_id) VALUES (?,?)',[$task,$user]);
    }
    public function clearNotifications(int $id, ?int $user = null): void { $this->connection->delete('crm_task_notifications',['task_id'=>$id]+($user === null ? [] : ['user_id'=>$user])); }
    public function history(string $kind, int $id, ?int $actor, array $details, string $now, array $extra = []): void {
        if (!$details) {

            return;
        }
        $this->connection->insert('crm_' . $kind . '_history', [
            $kind . '_id' => $id,
            'author_id' => $actor,
            'happened_at' => $now,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ] + $extra);
    }

    public function latestCancellationDealId(int $orderId): ?int {
        $id = $this->connection->fetchOne('
            SELECT id FROM crm_deals
            WHERE source_order_id = :orderId
            ORDER BY id DESC LIMIT 1
        ', ['orderId' => $orderId]);

        return $id === false ? null : (int) $id;
    }
    public function historyFor(string $kind, int $id): array {
        $rows = $this->connection->fetchAllAssociative('SELECT h.*,u.display_name author_name FROM crm_'.$kind.'_history h LEFT JOIN identity_users u ON u.id=h.author_id WHERE h.'.$kind.'_id=? ORDER BY h.happened_at DESC,h.id DESC',[$id]);
        $texts = [];
        foreach ($rows as &$row) {
            $row['details'] = json_decode($row['details'],true) ?: [];
            foreach ($row['details'] as $detail) $texts[] = $detail;
        }
        unset($row);
        $texts = $this->historyText->format($texts);
        $index = 0;
        foreach ($rows as &$row) {
            foreach ($row['details'] as &$detail) $detail = $texts[$index++];
            unset($detail);
        }
        unset($row);

        return $rows;
    }
    public function requireCustomer(int $id): void { if (!$this->connection->fetchOne('SELECT 1 FROM customers WHERE id=?',[$id])) throw new \DomainException('Клиент не найден.'); }
    public function requireUser(int $id, ?int $existing = null): void {
        if (!$this->connection->fetchOne('SELECT 1 FROM identity_users WHERE id=? AND (enabled=1 OR id=?)',[$id,$existing ?? -1])) throw new \DomainException('Исполнитель недоступен.');
    }

    public function requireProduct(int $id, bool $existing): void {
        if (!$this->connection->fetchOne(
            'SELECT 1 FROM products p LEFT JOIN products parent ON parent.id=p.parent_id WHERE p.id=? AND p.parent_id IS NOT NULL' . ($existing ? '' : ' AND p.enabled=1 AND parent.enabled=1'),[$id])
        ) {
            throw new \DomainException('Товар недоступен.');
        }
    }

    public function products(): array {

        return $this->connection->fetchAllAssociative("
            SELECT p.id, CONCAT_WS(' ', g.name, p.name) name, p.parent_id,
                g.name group_name, p.name variant_name, p.price, p.unit
            FROM products p
            INNER JOIN products g ON g.id = p.parent_id
            WHERE p.enabled = 1 AND g.enabled = 1
            ORDER BY g.sorting, g.id, p.sorting, p.id
        ");
    }

    public function users(): array {

        return $this->connection->fetchAllAssociative('
            SELECT id, display_name, enabled
            FROM identity_users
            ORDER BY display_name, id
        ');
    }

    public function customers(): array {

        return $this->connection->fetchAllAssociative('
            SELECT id, name, phone, stacker_id
            FROM customers
            ORDER BY name, id
        ');
    }

    public function deals(string $state, string $q, ?int $manager, int $page): array {
        if (!in_array($state, ['active', 'cancelled', 'hidden', 'converted'], true)) {
            $state = 'active';
        }
        $where = 'd.state=?'; $args = [$state];
        if ($q !== '') {
            $where .= ' AND (c.name LIKE ? OR d.comment LIKE ? OR CAST(d.id AS CHAR)=?)';
            array_push($args, '%' . $q . '%', '%' .$q .'%', $q);
        }
        if ($manager) {
            $where .= ' AND (d.author_id=? OR EXISTS(SELECT 1 FROM crm_deal_contacts x WHERE x.deal_id=d.id AND x.executor_id=? AND x.completed_at IS NULL))';
            array_push($args, $manager, $manager);
        }
        $from = ' FROM crm_deals d LEFT JOIN customers c ON c.id = d.customer_id';
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*)' . $from . ' WHERE ' . $where, $args);
        $pages = max(1, (int) ceil($count/30));
        $page = min(max(1, $page), $pages);
        $rows = $this->connection->fetchAllAssociative('
            SELECT d.*, c.name customer_name, c.phone, u.display_name author_name, ct.due_at, ct.comment contact_comment, cu.display_name executor_name ' .
            $from . ' 
            LEFT JOIN identity_users u ON u.id = d.author_id 
            LEFT JOIN crm_deal_contacts ct ON ct.id = (
                SELECT MAX(x.id) FROM crm_deal_contacts x WHERE x.deal_id=d.id AND x.completed_at IS NULL
            ) 
            LEFT JOIN identity_users cu ON cu.id=ct.executor_id 
            WHERE ' . $where . ' 
            ORDER BY ct.due_at IS NULL, ct.due_at, d.created_at DESC, d.id DESC 
            LIMIT 30 OFFSET '.(($page-1)*30),$args);

        return ['rows'=>$rows,'current'=>$page,'pages'=>$pages,'total'=>$count,'state'=>$state];
    }

    public function tasks(int $actor, bool $director, bool $archive, TaskFilterDto $filter): array {
        $args = [];
        $where = [];
        if ($archive) {
            $where[] = match ($filter->state) {
                'completed' => 't.completed_at IS NOT NULL',
                'cancelled' => 't.cancelled=1',
                'hidden' => 't.hidden=1',
                default => '(t.completed_at IS NOT NULL OR t.cancelled=1 OR t.hidden=1)',
            };
        } else {
            $where[] = '(t.completed_at IS NULL AND t.cancelled=0 AND t.hidden=0 OR EXISTS('
                . 'SELECT 1 FROM crm_task_notifications n WHERE n.task_id=t.id AND n.user_id=?))';
            $args[] = $actor;
        }
        if (!$director) {
            $participant = 'EXISTS(SELECT 1 FROM crm_task_participants p WHERE p.task_id=t.id AND p.user_id=?)';
            if (!$archive && $filter->scope === 'mine') {
                $where[] = "(t.executor_id=? OR EXISTS(SELECT 1 FROM crm_task_participants p "
                    . "WHERE p.task_id=t.id AND p.user_id=? AND p.kind='coexecutor') OR EXISTS("
                    . 'SELECT 1 FROM crm_task_notifications n WHERE n.task_id=t.id AND n.user_id=?))';
                array_push($args, $actor, $actor, $actor);
            } elseif (!$archive && $filter->scope === 'from-me') {
                $where[] = '(t.author_id=? OR ' . $participant . ')';
                array_push($args, $actor, $actor);
            } else {
                $where[] = '(t.author_id=? OR t.executor_id=? OR ' . $participant . ')';
                array_push($args, $actor, $actor, $actor);
            }
        }
        if ($filter->executorId !== null) {
            $where[] = "(t.executor_id=? OR EXISTS(SELECT 1 FROM crm_task_participants p "
                . "WHERE p.task_id=t.id AND p.user_id=? AND p.kind='coexecutor'))";
            array_push($args, $filter->executorId, $filter->executorId);
        }
        if ($filter->authorId !== null) {
            $where[] = 't.author_id=?';
            $args[] = $filter->authorId;
        }
        if ($filter->q !== '') {
            $where[] = '(t.text LIKE ? OR t.last_comment LIKE ?)';
            array_push($args, '%' . $filter->q . '%', '%' . $filter->q . '%');
        }
        $sql = implode(' AND ', $where);
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM crm_tasks t WHERE ' . $sql, $args);
        $pages = max(1, (int) ceil($count / 30));
        $page = min(max(1, $filter->page), $pages);
        $rows = $this->connection->fetchAllAssociative(
            "
                SELECT t.*, u.display_name author_name, e.display_name executor_name,
                    EXISTS(
                        SELECT 1 FROM crm_task_notifications n
                        WHERE n.task_id = t.id AND n.user_id = ?
                    ) unread
                FROM crm_tasks t
                LEFT JOIN identity_users u ON u.id = t.author_id
                LEFT JOIN identity_users e ON e.id = t.executor_id
                WHERE {$sql}
                ORDER BY unread DESC, t.due_at IS NULL, t.due_at, t.id DESC
                LIMIT 30 OFFSET
            " . (($page - 1) * 30),
            [$actor, ...$args],
        );

        return ['rows' => $rows, 'current' => $page, 'pages' => $pages, 'total' => $count];
    }

    public function counters(string $day): array { return $this->connection->fetchAssociative('SELECT * FROM crm_counters WHERE day=?',[$day]) ?: ['calls'=>0,'new_calls'=>0,'visitors'=>0,'new_visitors'=>0]; }

    public function incrementCounter(string $day, string $kind): void {
        if (!in_array($kind,['calls','new_calls','visitors','new_visitors'],true)) throw new \DomainException('Неизвестный счётчик.');
        $this->connection->executeStatement('INSERT INTO crm_counters(day,'.$kind.') VALUES (?,1) ON DUPLICATE KEY UPDATE '.$kind.'='.$kind.'+1',[$day]);
    }
    public function files(int $task): array { return $this->connection->fetchAllAssociative('SELECT * FROM crm_task_files WHERE task_id=? AND removed=0 ORDER BY id',[$task]); }

    public function file(int $task, int $id): array { return $this->connection->fetchAssociative('SELECT * FROM crm_task_files WHERE task_id=? AND id=? AND removed=0',[$task,$id]) ?: throw new NotFoundHttpException('Файл не найден.'); }

    public function addFile(array $data): int { $this->connection->insert('crm_task_files',$data); return (int)$this->connection->lastInsertId(); }

    public function removeFile(int $id): void { $this->connection->update('crm_task_files',['removed'=>1],['id'=>$id]); }

    public function orderHistory(int $order, array $history): void {
        foreach ($history as $entry) $this->connection->insert('order_status_history',['order_id'=>$order,'status'=>'new','changed_at'=>$entry['happened_at'],'comment'=>'Сделка: '.implode("\n",$entry['details']),'user_id'=>$entry['author_id'],'imported_at'=>date('Y-m-d H:i:s')]);
    }
}
