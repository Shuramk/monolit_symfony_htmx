<?php

declare(strict_types=1);

namespace App\Domain\Product\ReadModel\Dtos;

final readonly class ProductCatalogItemDto {
    public function __construct(
        public int $id,
        public int $parent,
        public ?string $parentName,
        public string $name,
        public string $measure,
        public string $storageOffice,
        public string $storageWorkshop,
        public string $storageThird,
        public string $price,
        public int $type,
        public int $enabled,
    ) {
    }
}
