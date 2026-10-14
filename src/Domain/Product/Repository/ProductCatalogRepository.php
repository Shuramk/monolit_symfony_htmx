<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\ReadModel\Dtos\ProductCatalogItemDto;
use App\Domain\Product\ReadModel\Dtos\ProductDetailsDto;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class ProductCatalogRepository {
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    /** @return list<ProductCatalogItemDto> */
    public function search(string $query, bool $includeDisabled): array {
        $builder = $this->connection->createQueryBuilder()
            ->select("product.id", "COALESCE(product.parent_id, 0) AS parent", "parent.name AS parentName", "product.name", "product.unit AS measure", "product.storage_office AS storageOffice", "product.storage_workshop AS storageWorkshop", "product.storage_third AS storageThird", "product.price", "product.type", "product.enabled")
            ->from("products", "product")
            ->leftJoin("product", "products", "parent", "parent.id = product.parent_id")
            ->orderBy("product.sorting")
            ->addOrderBy("product.id")
            ->setMaxResults(200);

        if (!$includeDisabled) {
            $builder->andWhere("product.enabled = 1");
        }

        $query = trim($query);

        if ($query !== "") {
            $builder->andWhere("(product.name LIKE :query OR parent.name LIKE :query)")->setParameter("query", "%".$query."%");
        }

        return $this->serializer->denormalize($builder->executeQuery()->fetchAllAssociative(), ProductCatalogItemDto::class."[]");
    }

    public function getById(int $id): ?ProductDetailsDto {
        $product = $this->connection->createQueryBuilder()
            ->select("product.id", "COALESCE(product.parent_id, 0) AS parent", "parent.name AS parentName", "product.name", "product.name_uk AS nameUk", "product.unit AS measure", "product.storage_office AS storageOffice", "product.storage_workshop AS storageWorkshop", "product.storage_third AS storageThird", "product.price", "product.type", "product.pallets", "product.inventory_name AS inventoryName", "product.forms", "product.weight_per_unit AS weightPerUnit", "product.min_quantity AS minQuantity", "product.enabled")
            ->from("products", "product")
            ->leftJoin("product", "products", "parent", "parent.id = product.parent_id")
            ->where("product.id = :id")
            ->setParameter("id", $id)
            ->executeQuery()
            ->fetchAssociative();

        if ($product === false) {
            return null;
        }

        return $this->serializer->denormalize($product, ProductDetailsDto::class);
    }

    /** @return list<array{id: int, name: string}> */
    public function findProductGroups(): array {
        return $this->connection->fetchAllAssociative(
            "SELECT id, name FROM products WHERE parent_id IS NULL AND enabled = 1 ORDER BY sorting, id",
        );
    }

    /** @return array<int, list<array{id: int, parent_id: int, name: string, unit: string, price: string}>> */
    public function findProductsByParent(): array {
        $productsByParent = [];

        foreach ($this->connection->fetchAllAssociative("SELECT id, parent_id, name, unit, price FROM products WHERE parent_id IS NOT NULL AND enabled = 1 ORDER BY sorting, id") as $product) {
            $productsByParent[(int) $product["parent_id"]][] = $product;
        }

        return $productsByParent;
    }

    /** @return list<array{id: int, name: string, unit: string, price: string}> */
    public function findProductsByParentId(int $parentId): array {
        return $this->connection->fetchAllAssociative(
            "SELECT id, name, unit, price FROM products WHERE parent_id = :parentId AND enabled = 1 ORDER BY sorting, id",
            ["parentId" => $parentId],
        );
    }
}
