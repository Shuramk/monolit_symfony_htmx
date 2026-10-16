<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\Services\OrderChangelog;
use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Warehouse\Repository\ShippingRepository;
use App\Domain\Warehouse\Repository\WarehouseRepository;
use App\Domain\Warehouse\UseCases\Dtos\ShippingTaskDto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShippingTasks {
    public function __construct(
        private ShippingRepository $repository,
        private OrderRepository $orders,
        private ProductRepository $products,
        private WarehouseRepository $warehouse,
        private OrderChangelog $changelog,
    ) {}

    public function create(int $orderId, ShippingTaskDto $dto, int $actor, \DateTimeImmutable $now): int {
        $now = $now->setTimezone(new \DateTimeZone('Europe/Kyiv'));
        if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $dto->operationId)) {
            throw new \DomainException('Некорректный идентификатор операции.');
        }
        $key = 'shipping:' . $actor . ':' . strtolower($dto->operationId);
        $fingerprint = hash('sha256', json_encode([$orderId, $dto], JSON_THROW_ON_ERROR));

        return $this->orders->transactional(function () use ($orderId, $dto, $actor, $now, $key, $fingerprint): int {
            $order = $this->orders->findForUpdate($orderId) ?? throw new NotFoundHttpException('Заказ не найден.');
            $previous = $this->repository->operation($key);
            if ($previous !== null) {
                if (($previous['payload']['requestHash'] ?? '') !== $fingerprint) {
                    throw new ConflictHttpException('Идентификатор операции уже использован для других данных.');
                }

                return (int) $previous['id'];
            }
            $order->assertVersion($dto->version);
            $this->assertOrder($order);
            $lines = $this->validatedLines($orderId, $dto);
            $carrierId = $dto->pickup ? 0 : $dto->carrierId;
            $this->repository->requireCarrier($carrierId);
            $id = $this->repository->create(
                $orderId, $this->performanceDate($dto), trim($dto->comment), [
                    'storage' => $dto->storage,
                    'carrierID' => $carrierId ?: null,
                    'shipmentPrice' => $carrierId ? $dto->shippingPrice : '0.00',
                    'allowed' => $dto->allowed ? 1 : null,
                    'collected' => 0,
                    'requestHash' => $fingerprint,
                    'authorId' => $actor,
                ], $key, $now->format('Y-m-d H:i:s'),
            );
            $this->repository->replaceLines($id, $lines);
            $order->shippingChanged($now);
            $this->orders->save($order);
            $this->changelog->record(
                $orderId, 'shipping_created', 'Добавлена задача на отгрузку №' . $id, $actor, $now,
                ['taskId' => $id, 'timezone' => 'Europe/Kyiv'],
            );
            if ($dto->allowed) {
                $this->repository->approvalHistory($orderId, $id, $order->getStatus()->value, true, $actor, $now);
            }

            return $id;
        });
    }

    public function edit(int $id, ShippingTaskDto $dto, int $actor, \DateTimeImmutable $now): void {
        $now = $now->setTimezone(new \DateTimeZone('Europe/Kyiv'));
        $this->orders->transactional(function () use ($id, $dto, $actor, $now): void {
            [$task, $order] = $this->lockTask($id);
            $this->assertOpen($task, $dto->version);
            $this->assertOrder($order);
            $lines = $this->validatedLines($order->getId(), $dto, $id);
            $carrierId = $dto->pickup ? 0 : $dto->carrierId;
            $this->repository->requireCarrier($carrierId);
            $date = $this->performanceDate($dto);
            $changes = $this->editChanges($task, $dto, $lines, $date);
            if ($changes === []) {

                return;
            }
            $payload = $task['payload'];
            $payload['storage'] = $dto->storage;
            $payload['carrierID'] = $carrierId ?: null;
            $payload['shipmentPrice'] = $carrierId ? $dto->shippingPrice : '0.00';
            $this->repository->update($task, [
                'comment' => trim($dto->comment), 'performance_date' => $date,
            ], $payload);
            if ($this->quantities($task['lines']) !== $this->quantities($lines)) {
                $this->repository->replaceLines($id, $lines);
            }
            $order->shippingChanged($now);
            $this->orders->save($order);
            $this->changelog->record(
                $order->getId(), 'shipping_edited',
                'Изменена задача на отгрузку №' . $id . '. ' . implode('; ', $changes), $actor, $now,
                ['taskId' => $id, 'timezone' => 'Europe/Kyiv', 'before' => $task, 'lines' => $lines, 'changes' => $changes],
            );
        });
    }

    public function act(int $id, int $version, string $action, int $actor, bool $canChooseStorage, \DateTimeImmutable $now): void {
        $now = $now->setTimezone(new \DateTimeZone('Europe/Kyiv'));
        $this->orders->transactional(function () use ($id, $version, $action, $actor, $canChooseStorage, $now): void {
            [$task, $order] = $this->lockTask($id);
            $this->assertOpen($task, $version);
            $payload = $task['payload'];
            $storage = (int) ($payload['storage'] ?? 2);
            if ($action !== 'delete') {
                $this->assertOrder($order);
                if (!$canChooseStorage && $storage !== 2) {
                    throw new \DomainException('Для вашего пользователя доступен только склад Цех.');
                }
            }
            $fields = [];
            $status = null;
            if ($action === 'collect') {
                if ((int) ($payload['collected'] ?? 0) === 1) {

                    return;
                }
                $payload['collected'] = 1;
                $message = 'Собрана задача на отгрузку №' . $id;
            } elseif ($action === 'delete') {
                $fields['status'] = 2;
                $message = 'Удалена задача на отгрузку №' . $id;
            } elseif ($action === 'complete') {
                $this->complete($task, $order, $storage, $actor, $now);
                $fields = ['status' => 1, 'completed_at' => $now->format('Y-m-d H:i:s')];
                $status = $this->completedOrderStatus($order->getId());
                $message = 'Выполнена задача на отгрузку №' . $id;
            } else {
                throw new \DomainException('Неизвестное действие с задачей.');
            }
            $this->repository->update($task, $fields, $payload);
            $order->shippingChanged($now, $status);
            $this->orders->save($order);
            $this->changelog->record(
                $order->getId(), 'shipping_' . $action, $message, $actor, $now,
                ['taskId' => $id, 'timezone' => 'Europe/Kyiv'],
            );
        });
    }

    private function complete(array $task, Order $order, int $storage, int $actor, \DateTimeImmutable $now): void {
        $this->warehouse->requireImported();
        $source = $this->repository->orderSource($order->getId());
        $remaining = [];
        $prices = [];
        foreach ($source['lines'] as $line) {
            $productId = (int) $line['product_id'];
            $prices[$productId] = (string) BigDecimal::of($line['price'] ?? '0')->toScale(4, RoundingMode::HalfUp);
            $remaining[$productId] = Decimal::fromString($line['quantity'], 3)
                ->subtract(Decimal::fromString((string) ($source['shipped'][$productId] ?? '0'), 3));
        }
        $lines = [];
        foreach ($task['lines'] as $line) {
            $productId = (int) $line['product_id'];
            $quantity = Decimal::fromString($line['quantity'], 3);
            if ($quantity->isZero() || $quantity->isNegative() || !isset($remaining[$productId])) {
                throw new \DomainException('Проверьте состав задачи: товар отсутствует в заказе или количество некорректно.');
            }
            $remaining[$productId] = $remaining[$productId]->subtract($quantity);
            if ($remaining[$productId]->isNegative()) {
                throw new ConflictHttpException('Количество к отгрузке изменилось. Отредактируйте задачу.');
            }
            $lines[$productId] = isset($lines[$productId])
                ? $lines[$productId]->add($quantity)
                : $quantity;
        }
        if ($lines === []) {
            throw new \DomainException('Состав задачи пуст.');
        }
        ksort($lines, SORT_NUMERIC);
        $shipmentLines = [];
        foreach ($lines as $productId => $quantity) {
            $product = $this->products->findForUpdate($productId) ?? throw new \DomainException('Товар не найден.');
            $product->changeStock($storage, Decimal::zero(3)->subtract($quantity), $now);
            $this->products->save($product);
            $this->repository->updateLinePrice((int) $task['id'], $productId, $prices[$productId]);
            $this->warehouse->record(
                $product, $actor, 'deleted', $quantity, $storage,
                'Заказ №' . $order->getId() . '. ' . $task['comment'], null, null,
                'shipping-complete:' . $task['id'] . ':' . $productId,
                ['taskId' => (int) $task['id']], (int) $task['id'],
            );
            $shipmentLines[] = ['product_id' => $productId, 'quantity' => $quantity->format(3)];
        }
        $this->repository->recordShipment(
            $order->getId(), (int) $task['id'],
            empty($task['payload']['carrierID']) ? null : (int) $task['payload']['carrierID'],
            $shipmentLines, $now->format('Y-m-d H:i:s'),
        );
    }

    private function completedOrderStatus(int $orderId): OrderStatus {
        $source = $this->repository->orderSource($orderId);
        foreach ($source['lines'] as $line) {
            $shipped = Decimal::fromString((string) ($source['shipped'][(int) $line['product_id']] ?? '0'), 3);
            if (!$shipped->equals(Decimal::fromString($line['quantity'], 3))) {

                return OrderStatus::PartiallyShipped;
            }
        }
        $total = Decimal::fromString($this->changelog->snapshot($orderId)['total'], 2);
        $paid = Decimal::fromString($source['prepayment'], 2)->add(Decimal::fromString($source['deliveryPaid'], 2));

        return $paid->equals($total) ? OrderStatus::PaidAndDelivered : OrderStatus::Delivered;
    }

    private function lockTask(int $id): array {
        $initial = $this->repository->task($id);
        if ($initial['order_id'] === null) {
            throw new \DomainException('Эта задача относится к перемещению, а не к заказу.');
        }
        $order = $this->orders->findForUpdate((int) $initial['order_id']) ?? throw new NotFoundHttpException('Заказ не найден.');
        $task = $this->repository->task($id, lock: true);

        return [$task, $order];
    }

    private function assertOpen(array $task, int $version): void {
        if ((int) $task['status'] !== 0 || (int) $task['version'] !== $version) {
            throw new ConflictHttpException('Задача уже изменена или выполнена. Обновите страницу.');
        }
    }

    private function assertOrder(Order $order): void {
        if ($order->getStatus() === OrderStatus::Cancelled) {
            throw new \DomainException('Заказ отменён. Отгрузка недоступна.');
        }
    }

    private function performanceDate(ShippingTaskDto $dto): string {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dto->date . ' ' . $dto->time);
        if ($date === false || $date->format('Y-m-d H:i') !== $dto->date . ' ' . $dto->time) {
            throw new \DomainException('Некорректная дата отгрузки.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function validatedLines(int $orderId, ShippingTaskDto $dto, int $except = 0): array {
        $source = $this->repository->orderSource($orderId);
        $planned = $this->repository->planned($orderId, $except);
        $catalog = [];
        foreach ($source['lines'] as $line) {
            $catalog[(int) $line['product_id']] = $line;
        }
        $lines = [];
        foreach ($dto->lines as $input) {
            if (!is_array($input)) {
                throw new \DomainException('Некорректная строка продукции.');
            }
            $id = filter_var($input['productId'] ?? null, FILTER_VALIDATE_INT);
            if (!is_string($input['quantity'] ?? null) && !is_int($input['quantity'] ?? null) && !is_float($input['quantity'] ?? null)) {
                throw new \DomainException('Некорректное количество.');
            }
            $value = str_replace(',', '.', trim((string) ($input['quantity'] ?? '')));
            if (!$id || !isset($catalog[$id]) || isset($lines[$id]) || !preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D', $value)) {
                throw new \DomainException('Проверьте товар и количество в задаче.');
            }
            $quantity = Decimal::fromString($value, 3);
            if ($quantity->isZero()) {
                continue;
            }
            $line = $catalog[$id];
            $remaining = Decimal::fromString($line['quantity'], 3)
                ->subtract(Decimal::fromString((string) ($source['shipped'][$id] ?? '0'), 3))
                ->subtract(Decimal::fromString((string) ($planned[$id] ?? '0'), 3));
            if ($quantity->scaledValue() > $remaining->scaledValue()) {
                throw new \DomainException('Количество превышает остаток заказа с учётом других задач на отгрузку.');
            }
            $lines[$id] = [
                'product_id' => $id, 'quantity' => $quantity->format(3),
                'price' => (string) BigDecimal::of($line['price'])->toScale(4, RoundingMode::HalfUp),
            ];
        }
        if ($lines === []) {
            throw new \DomainException('Выберите хотя бы одну позицию для отгрузки.');
        }

        return array_values($lines);
    }

    private function editChanges(array $task, ShippingTaskDto $dto, array $lines, string $date): array {
        $payload = $task['payload'];
        $changes = [];
        if (trim($dto->comment) !== (string) $task['comment']) {
            $changes[] = 'Изменён комментарий';
        }
        if ($date !== $task['performance_date']) {
            $changes[] = 'Дата: ' . $task['performance_date'] . ' → ' . $date;
        }
        if ($dto->storage !== (int) ($payload['storage'] ?? 2)) {
            $changes[] = 'Склад: ' . ($dto->storage === 1 ? 'Офис' : 'Цех');
        }
        $carrierId = $dto->pickup ? 0 : $dto->carrierId;
        if ($carrierId !== (int) ($payload['carrierID'] ?? 0)) {
            $changes[] = 'Изменён перевозчик / самовывоз';
        }
        $price = $carrierId ? $dto->shippingPrice : '0';
        if (!Decimal::fromString($price, 2)->equals(Decimal::fromString((string) ($payload['shipmentPrice'] ?? '0'), 2))) {
            $changes[] = 'Стоимость доставки: ' . $price . ' грн';
        }
        $old = [];
        foreach ($task['lines'] as $line) {
            $productId = (int) $line['product_id'];
            $old[$productId] = ($old[$productId] ?? Decimal::zero(3))->add(Decimal::fromString($line['quantity'], 3));
        }
        $new = array_column($lines, 'quantity', 'product_id');
        $ids = array_unique(array_merge(array_keys($old), array_keys($new)));
        foreach ($ids as $productId) {
            $before = $old[$productId] ?? Decimal::zero(3);
            $after = Decimal::fromString((string) ($new[$productId] ?? '0'), 3);
            if (!$before->equals($after)) {
                $changes[] = 'Количество товара №' . $productId . ': ' . $before->format(3) . ' → ' . $after->format(3);
            }
        }

        return $changes;
    }

    private function quantities(array $lines): array {
        $result = [];
        foreach ($lines as $line) {
            $id = (int) $line['product_id'];
            $quantity = Decimal::fromString($line['quantity'], 3);
            $result[$id] = Decimal::fromString($result[$id] ?? '0', 3)->add($quantity)->format(3);
        }
        ksort($result, SORT_NUMERIC);

        return $result;
    }
}
