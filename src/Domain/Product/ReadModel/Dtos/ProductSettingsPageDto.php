<?php declare(strict_types=1);

namespace App\Domain\Product\ReadModel\Dtos;

final readonly class ProductSettingsPageDto {
    public function __construct(public array $groups, public string $query, public bool $includeDisabled) {}
}
