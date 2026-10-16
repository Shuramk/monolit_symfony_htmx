<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ShippingImportRepository {
    public function __construct(
        #[Autowire('@doctrine.dbal.legacy_connection')] private Connection $legacy,
        #[Autowire('@doctrine.dbal.app_connection')] private Connection $app,
    ) {}

    public function importCarriers(): int {
        $rows = $this->legacy->fetchAllAssociative('SELECT * FROM sklad_carriers ORDER BY carrierID');

        return $this->app->transactional(function () use ($rows): int {
            $count = 0;
            foreach ($rows as $row) {
                $count += $this->app->executeStatement('
                    INSERT IGNORE INTO shipping_carriers (id, name, phone, priority, payload)
                    VALUES (:id, :name, :phone, :priority, :payload)
                ', [
                    'id' => $row['carrierID'],
                    'name' => $row['carrierName'],
                    'phone' => $row['carrierPhone'],
                    'priority' => $row['priority'],
                    'payload' => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
            }

            return $count;
        });
    }
}
