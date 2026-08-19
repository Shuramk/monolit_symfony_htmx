<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

final readonly class OrderEditViewDto {
    /**
     * @param array<string, mixed> $order
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $productGroups
     * @param array<int, list<array<string, mixed>>> $productsByParent
     */
    public function __construct(
        public array $order,
        public array $lines,
        public array $productGroups,
        public array $productsByParent,
    ) {}
}
