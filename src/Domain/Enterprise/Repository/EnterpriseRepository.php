<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Repository;

use App\Domain\Enterprise\Entities\Enterprise;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EnterpriseRepository {
    public function __construct(private EntityManagerInterface $entityManager) {
    }

    public function getById(int $id): ?Enterprise {
        return $this->entityManager->find(Enterprise::class, $id);
    }

    public function save(Enterprise $enterprise): void {
        $this->entityManager->persist($enterprise);
        $this->entityManager->flush();
    }
}
