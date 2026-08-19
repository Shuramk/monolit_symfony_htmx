<?php

declare(strict_types=1);

namespace App\Domain\Order\Repository;

use App\Domain\Order\Entities\Order;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

final readonly class OrderRepository {
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function transactional(callable $operation): mixed {
        try {
            return $this->entityManager->getConnection()->transactional($operation);
        } catch (\Throwable $exception) {
            $this->entityManager->clear();
            throw $exception;
        }
    }

    public function findForUpdate(int $id): ?Order {
        $order = $this->entityManager->createQuery('SELECT o, item FROM App\Domain\Order\Entities\Order o LEFT JOIN o.items item WHERE o.id = :id ORDER BY item.position')
            ->setParameter('id', $id)->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
        if ($order !== null) {
            // Refresh payments under the same lock, without multiplying items by payments.
            $this->entityManager->createQuery('SELECT o, payment FROM App\Domain\Order\Entities\Order o LEFT JOIN o.payments payment WHERE o.id = :id')
                ->setParameter('id', $id)->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
        }

        return $order;
    }

    public function nextOrderId(\DateTimeImmutable $now): int {
        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new \LogicException('Номер заказа должен выдаваться в транзакции.');
        }
        $date = $now->format("ymd");
        $connection->executeStatement("INSERT INTO order_number_sequences (order_date, last_number) SELECT :date, LAST_INSERT_ID(COALESCE(MAX(CAST(SUBSTRING(CAST(id AS CHAR), 7) AS UNSIGNED)), 0) + 1) FROM orders WHERE CAST(id AS CHAR) LIKE CONCAT(:date, '%') ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)", ["date" => $date]);
        $number = (int) $connection->fetchOne("SELECT LAST_INSERT_ID()");

        return (int) sprintf("%s%02d", $date, $number);
    }

    public function save(Order $order): void {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Сохранение заказа должно выполняться в транзакции.');
        }
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $stagePositions = false;
        if ($this->entityManager->contains($order)) {
            foreach ($order->getItems() as $item) {
                $original = $unitOfWork->getOriginalEntityData($item);
                if ($item->getId() === null || ($original['position'] ?? null) !== $item->getPosition()) {
                    $stagePositions = true;
                    break;
                }
            }
        }
        if ($stagePositions) {
            // MariaDB checks unique positions before ORM orphan deletions.
            $this->entityManager->getConnection()->executeStatement('UPDATE order_items SET position = -position WHERE order_id = :id', ['id' => $order->getId()]);
            foreach ($order->getItems() as $item) {
                $original = $unitOfWork->getOriginalEntityData($item);
                if (isset($original['position'])) {
                    $unitOfWork->setOriginalEntityProperty(spl_object_id($item), 'position', -$original['position']);
                }
            }
        }
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }

    public function hasRecordedShipment(int $id): bool {

        return $this->entityManager->getConnection()->fetchOne('
            SELECT 1
            FROM order_shipment_items item
            INNER JOIN order_shipments shipment ON shipment.id = item.shipment_id
            WHERE shipment.order_id = :id
            LIMIT 1
        ', ['id' => $id]) !== false;
    }

    public function cancellationSource(int $id): array {
        $connection = $this->entityManager->getConnection();
        $source = $connection->fetchAssociative('
            SELECT id, customer_id, customer_name, customer_phone, manager_id,
                created_at, desired_at, shipping_address, comments, markup
            FROM orders
            WHERE id = :id
        ', ['id' => $id]);
        if ($source === false) {
            throw new \LogicException('Заказ не найден внутри транзакции.');
        }
        $source['lines'] = $connection->fetchAllAssociative('
            SELECT product_id, product_name, product_group_name, unit, quantity, unit_price
            FROM order_items
            WHERE order_id = :id
            ORDER BY position, id
        ', ['id' => $id]);

        return $source;
    }
}
