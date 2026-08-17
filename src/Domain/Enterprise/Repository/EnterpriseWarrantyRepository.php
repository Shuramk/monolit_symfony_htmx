<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Repository;

use App\Domain\Enterprise\Exception\EnterpriseNotFound;
use Doctrine\DBAL\Connection;

final readonly class EnterpriseWarrantyRepository {
    public function __construct(private Connection $connection) {
    }

    public function getSourceHtmlByEnterpriseId(int $enterpriseId): string {
        $html = $this->connection->createQueryBuilder()
            ->select("source_html")
            ->from("enterprise_warranty_texts")
            ->where("enterprise_profile_id = :enterpriseId")
            ->setParameter("enterpriseId", $enterpriseId)
            ->executeQuery()
            ->fetchOne();

        if ($html === false) {
            throw new EnterpriseNotFound($enterpriseId);
        }

        return $html;
    }
}
