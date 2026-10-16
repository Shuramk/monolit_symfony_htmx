<?php

declare(strict_types=1);

namespace App\Domain\Customer\Repository;

use Doctrine\DBAL\Connection;

final readonly class CustomerViewRepository {
    public function __construct(private Connection $connection) {
    }

    /** @return list<array{id: int, name: string, phone: string|null}> */
    public function findCustomers(): array {
        return $this->connection->fetchAllAssociative("SELECT id, name, phone FROM customers ORDER BY name, id");
    }

    public function find(int $id): ?array {
        $row = $this->connection->fetchAssociative('SELECT id, name, phone FROM customers WHERE id = :id', ['id' => $id]);

        return $row === false ? null : $row;
    }

    public function search(string $query): array {
        $query = trim($query);
        if (mb_strlen($query) < 2) {

            return [];
        }
        $phone = preg_replace('/\D/', '', $query);
        if (strlen($phone) === 12 && str_starts_with($phone, '38')) {
            $phone = substr($phone, 2);
        }
        $name = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';

        return $this->connection->fetchAllAssociative('
            SELECT id, name, phone FROM customers
            WHERE name LIKE :name ESCAPE \'!\'
                OR (:hasPhone = 1 AND (
                    REGEXP_REPLACE(COALESCE(phone, \'\'), \'[^0-9]\', \'\') LIKE :phone
                    OR REGEXP_REPLACE(COALESCE(phone_secondary, \'\'), \'[^0-9]\', \'\') LIKE :phone
                    OR REGEXP_REPLACE(COALESCE(phone_tertiary, \'\'), \'[^0-9]\', \'\') LIKE :phone
                ))
            ORDER BY name, id LIMIT 20
        ', ['name' => $name, 'hasPhone' => strlen($phone) >= 3 ? 1 : 0, 'phone' => '%' . $phone . '%']);
    }
}
