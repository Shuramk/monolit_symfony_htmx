<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\Repository\OrderReturnRepository;
use App\Domain\Order\Services\OrderChangelog;
use App\Domain\Order\UseCases\Dtos\OrderReturnDto;
use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Warehouse\Repository\WarehouseRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OrderReturns {
    public function __construct(private OrderReturnRepository $repository, private OrderRepository $orders, private ProductRepository $products, private WarehouseRepository $warehouse, private OrderChangelog $changelog) {}

    public function draft(OrderReturnDto $dto, int $actor, \DateTimeImmutable $now): int {
        $items = $this->items($dto->items);
        $uuid = $this->uuid($dto->operationId);
        $hash = hash('sha256', json_encode([$actor, $items, $dto->comment], JSON_THROW_ON_ERROR));
        return $this->orders->transactional(function () use ($dto, $actor, $now, $items, $uuid, $hash): int {
            $this->repository->lockActor($actor);
            $this->repository->requireImported();
            $old = $this->repository->operation($uuid, true);
            if ($old) return $this->previous($old, $hash);
            foreach ($items as $id => $quantity) {
                $product = $this->products->findForUpdate($id);
                if (!$product || !$product->getParent()) throw new \DomainException('Товар не найден.');
            }
            // Recheck after product locks for a concurrent identical request.
            $old = $this->repository->operation($uuid, true);
            if ($old) return $this->previous($old, $hash);
            return $this->repository->createDraft(['user_id' => $actor, 'created_at' => $now->setTimezone(new \DateTimeZone('Europe/Kyiv'))->format('Y-m-d H:i:s'), 'comment' => $dto->comment, 'done' => 0, 'operation_id' => $uuid, 'request_hash' => $hash], $items);
        });
    }

    public function accept(int $orderId, OrderReturnDto $dto, int $actor, \DateTimeImmutable $now): int {
        $items = $this->items($dto->items);
        $uuid = $this->uuid($dto->operationId);
        $hash = hash('sha256', json_encode([$orderId, $actor, $dto->orderVersion, $dto->draftId, $items], JSON_THROW_ON_ERROR));
        $now = $now->setTimezone(new \DateTimeZone('Europe/Kyiv'));
        return $this->orders->transactional(function () use ($orderId, $dto, $actor, $now, $items, $uuid, $hash): int {
            // Acquire the order lock before the first consistent read: on MariaDB's
            // REPEATABLE READ a snapshot taken before waiting could miss another return.
            $order = $this->orders->findForUpdate($orderId) ?? throw new NotFoundHttpException('Заказ не найден.');
            $this->repository->requireImported();
            $this->warehouse->requireImported();
            $old = $this->repository->operation($uuid, false);
            if ($old) {
                return $this->previous($old, $hash);
            }

            $order->assertVersion($dto->orderVersion);
            if ($order->getStatus() === OrderStatus::Cancelled) {
                throw new \DomainException('Нельзя принять возврат по отменённому заказу.');
            }

            if ($dto->draftId !== null && $this->repository->draft($dto->draftId, true)['done']) {
                throw new ConflictHttpException('Заявка уже принята.');
            }

            $source = $this->repository->order($orderId);
            $prices = [];
            foreach ($source['items'] as $line) {
                $productId = (int) $line['product_id'];
                $price = Decimal::fromString($line['unit_price'], 4)->format(4);
                if (isset($prices[$productId]) && $prices[$productId] !== $price && isset($items[$productId])) {
                    throw new \DomainException('У одного товара в заказе разные цены. Исправьте заказ.');
                }

                $prices[$productId] = $price;
            }
            $shipped = $this->repository->shipped($orderId);
            $pending = $this->repository->pending($orderId);
            $lines = $locked = [];
            foreach ($items as $id => $value) {
                if (!isset($prices[$id])) {
                    throw new \DomainException('Товар не принадлежит заказу.');
                }

                $quantity = Decimal::fromString($value, 3);
                $available = Decimal::fromString((string) ($shipped[$id] ?? '0'), 3)->subtract(Decimal::fromString((string) ($pending[$id] ?? '0'), 3));
                if ($quantity->scaledValue() > $available->scaledValue()) {
                    throw new \DomainException('Количество возврата превышает доступный отгруженный остаток.');
                }

                $product = $this->products->findForUpdate($id);
                if (!$product || !$product->getParent()) {
                    throw new \DomainException('Товар не найден.');
                }

                $product->changeStock(2, $quantity, $now);
                $locked[] = $product;
                $lines[] = ['product_id' => $id, 'quantity' => $value, 'price' => $prices[$id]];
            }
            $id = $this->repository->create([
                'order_id' => $orderId,
                'draft_id' => $dto->draftId,
                'accepted_at' => $now->format('Y-m-d H:i:s'),
                'accepted_by' => $actor,
                'operation_id' => $uuid,
                'request_hash' => $hash
            ], $lines);
            foreach ($locked as $product) {
                $quantity = Decimal::fromString($items[$product->getId()], 3);
                $this->warehouse->record(
                    $product,
                    $actor,
                    'added',
                    $quantity,
                    2,
                    'Возврат №' . $id . '. Заказ №' . $orderId,
                    null,
                    null,
                    'return:' . $actor . ':' . $uuid . ':' . $product->getId(),
                    ['returnId' => $id, 'orderId' => $orderId],
                    now: $now,
                );
            }
            $this->products->saveAll($locked);
            $order->shippingChanged($now);
            $this->orders->save($order);
            $this->changelog->record(
                $orderId,
                'return_accepted',
                'Принят возврат №' . $id,
                $actor,
                $now,
                ['returnId' => $id, 'items' => $lines, 'timezone' => 'Europe/Kyiv'],
            );

            return $id;
        });
    }

    private function previous(array $row, string $hash): int {
        if ($row['request_hash'] !== $hash) {
            throw new ConflictHttpException('Идентификатор операции уже использован для других данных.');
        }

        return (int) $row['id'];
    }

    private function uuid(string $uuid): string {
        if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $uuid)) {
            throw new \DomainException('Некорректный идентификатор операции.');
        }

        return strtolower($uuid);
    }

    /** @return array<int,string> */
    private function items(array $rows): array {
        if (count($rows) > 500) {
            throw new \DomainException('Слишком много позиций.');
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \DomainException('Некорректная позиция.');
            }

            $id = $row['productId'] ?? null;
            $value = $row['quantity'] ?? '';
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                throw new \DomainException('Некорректный товар.');
            }

            if (!is_string($value) && !is_int($value)) {
                throw new \DomainException('Некорректное количество.');
            }

            $value = str_replace(',', '.', trim((string) $value));
            if ($value === '') {
                continue;
            }

            if ((int) $id <= 0 || !preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D', $value)) {
                throw new \DomainException('Количество: до 9 целых и 3 дробных знаков, без отрицательных значений.');
            }

            $quantity = Decimal::fromString($value, 3);
            if ($quantity->isZero()) {
                continue;
            }

            $id = (int) $id;
            $sum = Decimal::fromString($items[$id] ?? '0', 3)->add($quantity);
            if ($sum->scaledValue() > 999999999999) {
                throw new \DomainException('Слишком большое количество.');
            }

            $items[$id] = $sum->format(3);
        }
        if ($items === []) {
            throw new \DomainException('Укажите хотя бы один товар и количество больше нуля.');
        }

        ksort($items, SORT_NUMERIC);

        return $items;
    }
}
