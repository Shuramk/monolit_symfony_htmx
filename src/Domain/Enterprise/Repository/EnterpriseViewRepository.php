<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Repository;

use App\Domain\Enterprise\Exception\EnterpriseNotFound;
use App\Domain\Enterprise\ReadModel\Dtos\EnterpriseViewDto;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class EnterpriseViewRepository {
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function getById(int $id): EnterpriseViewDto {
        $enterprise = $this->connection->createQueryBuilder()
            ->select(
                "id",
                "display_name AS displayName",
                "short_name AS shortName",
                "edrpou_vat AS edrpouVat",
                "edrpou_pe AS edrpouPe",
                "address",
                "email",
            )
            ->from("enterprise_profiles")
            ->where("id = :id")
            ->setParameter("id", $id)
            ->executeQuery()
            ->fetchAssociative();

        if ($enterprise === false) {
            throw new EnterpriseNotFound($id);
        }

        $enterprise["phones"] = $this->connection->createQueryBuilder()
            ->select("id", "kind", "number")
            ->from("enterprise_phones")
            ->where("enterprise_profile_id = :id")
            ->orderBy("position")
            ->setParameter("id", $id)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->serializer->denormalize($enterprise, EnterpriseViewDto::class);
    }
}
