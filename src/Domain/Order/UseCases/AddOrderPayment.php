<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\Repository\OrderPaymentRepository;
use App\Domain\Order\Services\OrderChangelog;
use App\Domain\Order\UseCases\Dtos\AddOrderPaymentDto;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AddOrderPayment {
    public function __construct(private OrderRepository $orders, private OrderPaymentRepository $payments, private OrderChangelog $changelog) {}

    public function execute(int $id, AddOrderPaymentDto $dto, int $userId, \DateTimeImmutable $now, bool $shipment = false): int {
        return $this->orders->transactional(function () use ($id, $dto, $userId, $now, $shipment): int {
            $order = $this->orders->findForUpdate($id);
            if ($order === null) throw new NotFoundHttpException('Заказ не найден.');
            if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $dto->amount) !== 1) throw new \DomainException('Некорректная сумма оплаты.');
            $amount = Decimal::fromString($dto->amount, 2);
            $operationId = strtolower($dto->operationId);
            if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/', $operationId)) throw new \DomainException('Некорректный идентификатор операции.');
            $previous = $this->payments->findOperation($operationId);
            if ($previous !== null) {
                if (!$previous->matches($id, $amount, $dto->toCard, $userId, $shipment)) throw new \DomainException('Идентификатор оплаты уже использован для другой операции.');
                return $previous->getId();
            }
            $payment = $order->addPayment($this->payments->nextId(), $amount, $dto->toCard, $userId, $operationId, $now, $shipment);
            $this->orders->save($order);
            $this->changelog->record($id, $shipment ? 'delivery_payment_added' : 'payment_added', $payment->getMessage(), $userId, $now, ['paymentId'=>$payment->getId(), 'amount'=>$amount->format(), 'toCard'=>$dto->toCard, 'moneyTaken'=>1, 'shipment'=>$shipment]);
            return $payment->getId();
        });
    }
}
