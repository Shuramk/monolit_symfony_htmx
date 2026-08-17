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
            ->select("product.id", "product.parent", "parent.name AS parentName", "product.name", "product.measure", "product.storage1 AS storageOffice", "product.storage2 AS storageWorkshop", "product.storage3 AS storageThird", "product.price", "product.type", "product.enabled")
            ->from("sklad_products", "product")
            ->leftJoin("product", "sklad_products", "parent", "parent.id = product.parent")
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
            ->select("product.id", "product.parent", "parent.name AS parentName", "product.name", "product.name_uk AS nameUk", "product.measure", "product.storage1 AS storageOffice", "product.storage2 AS storageWorkshop", "product.storage3 AS storageThird", "product.price", "product.type", "product.pallets", "product.invName AS inventoryName", "product.forms", "product.weight_per_unit AS weightPerUnit", "product.minQuantity", "product.enabled")
            ->from("sklad_products", "product")
            ->leftJoin("product", "sklad_products", "parent", "parent.id = product.parent")
            ->where("product.id = :id")
            ->setParameter("id", $id)
            ->executeQuery()
            ->fetchAssociative();

        if ($product === false) {
            return null;
        }

        return $this->serializer->denormalize($product, ProductDetailsDto::class);
    }
}
