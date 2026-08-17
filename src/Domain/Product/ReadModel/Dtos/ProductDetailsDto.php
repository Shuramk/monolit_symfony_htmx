<?php

declare(strict_types=1);

namespace App\Domain\Product\ReadModel\Dtos;

final readonly class ProductDetailsDto {
    public function __construct(
        public int $id,
        public int $parent,
        public ?string $parentName,
        public string $name,
        public ?string $nameUk,
        public string $measure,
        public string $storageOffice,
        public string $storageWorkshop,
        public string $storageThird,
        public string $price,
        public int $type,
        public ?string $pallets,
        public ?string $inventoryName,
        public ?string $forms,
        public ?string $weightPerUnit,
        public ?string $minQuantity,
        public int $enabled,
    ) {
    }
}
