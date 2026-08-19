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
}
