<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Crm\UseCases\CancelOrderToCrm;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\Services\OrderChangelog;
use App\Domain\Order\UseCases\Dtos\ChangeOrderStatusDto;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ChangeOrderStatus {
    public function __construct(
        private OrderRepository $orders,
        private OrderChangelog $changelog,
        private CancelOrderToCrm $cancellation,
        private CrmRepository $crm,
    ) {}

    public function execute(int $id, ChangeOrderStatusDto $dto, int $actor, bool $main, \DateTimeImmutable $now): ?int {

        return $this->orders->transactional(function () use ($id, $dto, $actor, $main, $now): ?int {
            $order = $this->orders->findForUpdate($id);
            if ($order === null) {
                throw new NotFoundHttpException('Заказ не найден.');
            }
            $before = $order->getStatus();
            $reason = trim($dto->comment);
            $changed = $order->changeStatus(
                $dto->status, $dto->version, $main,
                $this->orders->hasRecordedShipment($id), $reason, $now,
            );
            if (!$changed) {

                return $before === OrderStatus::Cancelled ? $this->crm->latestCancellationDealId($id) : null;
            }
            $this->orders->save($order);
            $dealId = null;
            $message = 'Статус заказа изменён на «' . $dto->status->title() . '»';
            if ($dto->status === OrderStatus::Cancelled) {
                $dealId = $this->cancellation->execute($this->orders->cancellationSource($id), $actor, $reason, $now);
                $message .= '. Причина: ' . $reason;
            }
            $this->changelog->record($id, 'status_changed', $message, $actor, $now, [
                'beforeStatus' => $before->value,
                'afterStatus' => $dto->status->value,
                'reason' => $reason,
                'dealId' => $dealId,
            ]);

            return $dealId;
        });
    }
}
