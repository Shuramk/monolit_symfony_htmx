<?php declare(strict_types=1);

namespace App\Domain\Crm\UseCases;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ApproveShipment {
    public function __construct(private Connection $connection) {}

    public function execute(int $id, bool $allowed, bool $expectedAllowed, int $actor, \DateTimeImmutable $now): void {
        $this->connection->transactional(function () use ($id, $allowed, $expectedAllowed, $actor, $now): void {
            $task = $this->connection->fetchAssociative('SELECT * FROM warehouse_shipping_tasks WHERE id=? AND type=2 FOR UPDATE', [$id]);
            if (!$task) {
                throw new NotFoundHttpException('Задача на отгрузку не найдена.');
            }
            $payload = json_decode($task['payload'], true, 512, JSON_THROW_ON_ERROR);
            $current = (int)($payload['allowed'] ?? 0) === 1;
            if ((int)$task['status'] !== 0 || $current !== $expectedAllowed) {
                throw new ConflictHttpException('Задача уже изменена. Обновите страницу.');
            }
            if ($current === $allowed) {

                return;
            }
            $payload['allowed'] = $allowed ? 1 : null;
            $this->connection->update('warehouse_shipping_tasks', ['payload'=>json_encode($payload, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)], ['id'=>$id]);
            if ($task['order_id'] !== null) {
                $status = $this->connection->fetchOne('SELECT status FROM orders WHERE id=?', [$task['order_id']]);
                if ($status !== false) {
                    $this->connection->insert('order_status_history', ['order_id'=>$task['order_id'], 'task_id'=>$id, 'status'=>$status,
                        'changed_at'=>$now->format('Y-m-d H:i:s'), 'user_id'=>$actor, 'imported_at'=>$now->format('Y-m-d H:i:s'),
                        'comment'=>($allowed ? 'Согласована' : 'Снято согласование:').' задача на отгрузку №'.$id]);
                }
            }
        });
    }
}
