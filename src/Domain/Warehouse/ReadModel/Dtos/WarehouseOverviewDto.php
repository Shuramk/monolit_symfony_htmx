<?php declare(strict_types=1);
namespace App\Domain\Warehouse\ReadModel\Dtos;

final readonly class WarehouseOverviewDto {
    public function __construct(public array $sections) {}
}
