<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class WarehousePageDto {
    public function __construct(public array $products, public array $groups, public int $page, public int $pages, public int $total, public string $query, public int $groupId) {}
}
