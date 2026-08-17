<?php

declare(strict_types=1);

namespace App\Domain\Product\ReadModel;

use App\Domain\Product\ReadModel\Dtos\ProductCatalogItemDto;
use App\Domain\Product\ReadModel\Dtos\ProductDetailsDto;
use App\Domain\Product\Repository\ProductCatalogRepository;

final readonly class ProductCatalogView {
    public function __construct(private ProductCatalogRepository $productCatalogRepository) {
    }

    /** @return list<ProductCatalogItemDto> */
    public function search(string $query, bool $includeDisabled): array {
        return $this->productCatalogRepository->search($query, $includeDisabled);
    }

    public function getById(int $id): ?ProductDetailsDto {
        return $this->productCatalogRepository->getById($id);
    }
}
