<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class WarehouseDetailsDto {
    public function __construct(public array $group, public array $products, public array $tasks, public array $orders) {}
}
