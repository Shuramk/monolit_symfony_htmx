<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\Dtos\OrderFormDto;
use App\Domain\Customer\Repository\CustomerViewRepository;
use App\Domain\Product\Repository\ProductCatalogRepository;

final readonly class OrderFormView {
    public function __construct(
        private ProductCatalogRepository $products,
        private CustomerViewRepository $customers,
    ) {
    }

    public function get(): OrderFormDto {
        return new OrderFormDto(
            $this->products->findProductGroups(),
            $this->products->findProductsByParent(),
        );
    }

    /** @return list<array{id: int, name: string, unit: string, price: string}> */
    public function getProductOptions(int $parentId): array {
        return $this->products->findProductsByParentId($parentId);
    }

    /** @return list<array{id: int, name: string, phone: string}> */
    public function getCustomers(): array {
        return $this->customers->findCustomers();
    }
}
