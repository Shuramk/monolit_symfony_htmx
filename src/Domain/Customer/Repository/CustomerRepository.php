<?php

declare(strict_types=1);

namespace App\Domain\Customer\Repository;

use App\Domain\Customer\Entities\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class CustomerRepository {
    public function __construct(private EntityManagerInterface $entityManager) {
    }

    public function getById(int $id): Customer {
        $customer = $this->entityManager->find(Customer::class, $id);
        if ($customer === null) {
            throw new \DomainException('Клиент не найден.');
        }

        return $customer;
    }

    public function create(string $name, string $phone, string $key, \DateTimeImmutable $now): int {
        $connection = $this->entityManager->getConnection();

        return $connection->transactional(function () use ($connection, $name, $phone, $key, $now): int {
            $last = (int) $connection->fetchOne('SELECT last_id FROM customer_number_sequence WHERE id = 1 FOR UPDATE');
            $fingerprint = hash('sha256', json_encode([$name, $phone], JSON_THROW_ON_ERROR));
            $previous = $connection->fetchAssociative('
                SELECT customer_id, fingerprint FROM customer_creation_keys WHERE operation_key = :key
            ', ['key' => $key]);
            if ($previous !== false) {
                if ($previous['fingerprint'] !== $fingerprint) {
                    throw new ConflictHttpException('Повторная отправка содержит другие данные клиента.');
                }

                return (int) $previous['customer_id'];
            }
            $id = max($last, (int) $connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM customers')) + 1;
            $connection->update('customer_number_sequence', ['last_id' => $id], ['id' => 1]);
            $date = $now->format('Y-m-d H:i:s');
            $connection->insert('customers', [
                'id' => $id, 'name' => $name, 'phone' => $phone,
                'registered_at' => $date, 'imported_at' => $date, 'updated_at' => $date,
            ]);
            $connection->insert('customer_creation_keys', [
                'operation_key' => $key, 'customer_id' => $id, 'fingerprint' => $fingerprint,
            ]);

            return $id;
        });
    }
}
