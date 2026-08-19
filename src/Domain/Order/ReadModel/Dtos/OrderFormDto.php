<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

final readonly class OrderFormDto {
    /**
     * @param list<array{id: int, name: string}> $productGroups
     * @param array<int, list<array{id: int, parent_id: int, name: string, unit: string, price: string}>> $productsByParent
     */
    public function __construct(
        public array $productGroups,
        public array $productsByParent,
    ) {
    }
}
