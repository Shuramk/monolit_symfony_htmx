<?php declare(strict_types=1);

namespace App\Domain\Crm\UseCases;

use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Warehouse\Repository\ShippingRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ApproveShipment {
    public function __construct(private ShippingRepository $repository, private OrderRepository $orders) {}

    public function execute(int $id, bool $allowed, bool $expectedAllowed, int $actor, \DateTimeImmutable $now): void {
        $now = $now->setTimezone(new \DateTimeZone('Europe/Kyiv'));
        $this->orders->transactional(function () use ($id, $allowed, $expectedAllowed, $actor, $now): void {
            $initial = $this->repository->task($id);
            $order = null;
            if ($initial['order_id'] !== null) {
                $order = $this->orders->findForUpdate((int) $initial['order_id']);
                if ($order === null) {
                    throw new NotFoundHttpException('Заказ не найден.');
                }
            }
            $task = $this->repository->task($id, lock: true);
            $payload = $task['payload'];
            $current = (int) ($payload['allowed'] ?? 0) === 1;
            if ((int) $task['status'] !== 0 || $current !== $expectedAllowed) {
                throw new ConflictHttpException('Задача уже изменена. Обновите страницу.');
            }
            if ($current === $allowed) {

                return;
            }
            $payload['allowed'] = $allowed ? 1 : null;
            $this->repository->update($task, [], $payload);
            if ($order !== null) {
                $order->shippingChanged($now);
                $this->orders->save($order);
                $this->repository->approvalHistory(
                    $order->getId(), $id, $order->getStatus()->value, $allowed, $actor, $now,
                );
            }
        });
    }
}
