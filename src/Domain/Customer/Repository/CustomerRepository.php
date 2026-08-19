<?php

declare(strict_types=1);

namespace App\Domain\Customer\Repository;

use App\Domain\Customer\Entities\Customer;
use Doctrine\ORM\EntityManagerInterface;

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
}
