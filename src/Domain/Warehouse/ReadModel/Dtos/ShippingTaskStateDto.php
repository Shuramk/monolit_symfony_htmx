<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class ShippingTaskStateDto {
    public function __construct(
        public int $id,
        public int $version,
        public int $status,
        public bool $collected,
        public bool $allowed,
    ) {}
}
