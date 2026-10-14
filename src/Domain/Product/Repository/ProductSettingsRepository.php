<?php declare(strict_types=1);

namespace App\Domain\Product\Repository;

use Doctrine\DBAL\Connection;

final readonly class ProductSettingsRepository {
    public function __construct(private Connection $connection) {}

    public function groups(string $query, bool $includeDisabled): array {
        return $this->connection->fetchAllAssociative("SELECT p.*, (SELECT COUNT(*) FROM products v WHERE v.parent_id = p.id) AS variants_count FROM products p WHERE p.parent_id IS NULL AND (:hidden = 1 OR p.enabled = 1) AND (:query = '' OR p.name LIKE :search OR p.name_uk LIKE :search OR EXISTS (SELECT 1 FROM products v WHERE v.parent_id = p.id AND (v.name LIKE :search OR v.name_uk LIKE :search))) ORDER BY p.type, p.sorting, p.id",
            ['hidden' => (int) $includeDisabled, 'query' => $query, 'search' => '%'.$query.'%']);
    }

    public function find(int $id): ?array {
        $row = $this->connection->fetchAssociative('SELECT p.*, parent.name AS parent_name FROM products p LEFT JOIN products parent ON parent.id = p.parent_id WHERE p.id = ?', [$id]);
        return $row === false ? null : $row;
    }

    public function variants(int $parentId): array {
        return $this->connection->fetchAllAssociative('SELECT * FROM products WHERE parent_id = ? ORDER BY sorting, id', [$parentId]);
    }

    public function history(int $id): array {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM product_changelog WHERE product_id = ? ORDER BY id DESC LIMIT 50', [$id]);
        foreach ($rows as &$row) {
            $row['before'] = $row['before_snapshot'] === null ? null : json_decode($row['before_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $row['after'] = json_decode($row['after_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        }
        return $rows;
    }
}
