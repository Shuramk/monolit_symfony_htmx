<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class ShippingPageDto {
    public function __construct(
        public array $rows,
        public bool $archive,
        public string $query,
        public int $page,
        public int $pages,
        public int $total,
    ) {}
}
