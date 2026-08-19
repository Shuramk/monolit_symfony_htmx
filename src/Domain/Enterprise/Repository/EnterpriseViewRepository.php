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

    /** @return array<string, mixed>|null */
    public function findDocumentProfile(): ?array {
        $enterprise = $this->connection->fetchAssociative('
            SELECT id, legal_name_vat, legal_name_pe, display_name, address, email,
                edrpou_vat, edrpou_pe, payment_account_vat, payment_account_pe
            FROM enterprise_profiles
            ORDER BY id
            LIMIT 1
        ');
        if ($enterprise === false) {

            return null;
        }
        $enterprise['phones'] = $this->connection->fetchFirstColumn('
            SELECT number
            FROM enterprise_phones
            WHERE enterprise_profile_id = :profileId
            ORDER BY position, id
        ', ['profileId' => $enterprise['id']]);

        return $enterprise;
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
                "website",
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
