<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderListSort;

final readonly class OrderListPageDto {
    /**
     * @param list<OrderListItemDto> $orders
     * @param list<OrderStatus> $statuses
     * @param list<OrderStatus> $statusOptions
     * @param list<int> $managerIds
     * @param list<array{id: int|string, name: string}> $managerOptions
     * @param array<string, mixed> $filterParams
     */
    public function __construct(
        public array $orders,
        public string $query,
        public int $total,
        public int $page,
        public int $pages,
        public array $statuses,
        public array $statusOptions,
        public array $managerIds,
        public array $managerOptions,
        public array $filterParams,
        public ?OrderSearchDto $search,
        public array $productGroups,
        public array $productsByParent,
        public OrderListSort $sort,
        public string $direction,
    ) {
    }
}
