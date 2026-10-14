<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\Dtos\OrderListPageDto;
use App\Domain\Order\ReadModel\Dtos\OrderSearchDto;
use App\Domain\Product\Repository\ProductCatalogRepository;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderListSort;
use App\Domain\Order\Repository\OrderViewRepository;

final readonly class OrderListView {
    public function __construct(private OrderViewRepository $orderViewRepository, private ProductCatalogRepository $products) {}

    public function search(string $query, ?array $statuses = null, array $managers = [], int $page = 1, ?OrderSearchDto $criteria = null, string $sort = 'id', string $direction = 'DESC'): OrderListPageDto {
        $query = trim($query);
        $sort = OrderListSort::tryFrom($sort) ?? OrderListSort::Id;
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        if ($criteria !== null && $statuses === null) {
            $statuses = array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::cases());
        }
        $statuses ??= [OrderStatus::New->value, OrderStatus::InProgress->value, OrderStatus::ReadyForShipment->value, OrderStatus::Delivered->value, OrderStatus::PartiallyShipped->value];
        $selected = array_values(array_filter(OrderStatus::cases(), static fn (OrderStatus $status): bool => in_array($status->value, $statuses, true)));
        $values = array_map(static fn (OrderStatus $status): string => $status->value, $selected);
        $managerIds = array_values(array_unique(array_filter(array_map('intval', $managers), static fn (int $id): bool => $id > 0)));
        $total = $this->orderViewRepository->count($query, $values, $managerIds, $criteria);
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($page, $pages));

        return new OrderListPageDto(
            orders: $this->orderViewRepository->search($query, $values, $managerIds, $page, 50, $criteria, $sort, $direction),
            query: $query, total: $total, page: $page, pages: $pages,
            statuses: $selected, statusOptions: OrderStatus::cases(), managerIds: $managerIds,
            managerOptions: $this->orderViewRepository->findManagers(),
            filterParams: ['q' => $query, 'statuses' => $values === [] ? [''] : $values, 'managers' => $managerIds, 'sort' => $sort->value, 'direction' => $direction] + ($criteria === null ? [] : ['search' => get_object_vars($criteria)]),
            search: $criteria, productGroups: $this->products->findProductGroups(), productsByParent: $this->products->findProductsByParent(),
            sort: $sort, direction: $direction,
        );
    }
}
