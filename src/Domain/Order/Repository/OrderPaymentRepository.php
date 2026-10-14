<?php declare(strict_types=1);

namespace App\Domain\Order\Repository;

use App\Domain\Order\Entities\OrderPayment;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderPaymentRepository {
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function findOperation(string $operationId): ?OrderPayment {
        return $this->entityManager->getRepository(OrderPayment::class)->findOneBy(['operationId'=>$operationId]);
    }

    public function nextId(): int {
        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) throw new \LogicException('Номер оплаты должен выдаваться в транзакции.');
        $connection->executeStatement('INSERT INTO order_payment_numbers () VALUES ()');
        return (int)$connection->lastInsertId();
    }
}
