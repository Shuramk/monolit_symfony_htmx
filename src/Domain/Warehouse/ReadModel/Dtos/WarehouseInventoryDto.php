<?php declare(strict_types=1);
namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class WarehouseInventoryDto {
    public function __construct(public int $type, public array $groups, public string $date) {}
}
