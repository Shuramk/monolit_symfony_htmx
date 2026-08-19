<?php declare(strict_types=1);

namespace App\Domain\Product\ReadModel\Dtos;

final readonly class ProductSettingsEditDto {
    public function __construct(public ?array $product, public ?array $parent, public array $variants, public array $history) {}
}
