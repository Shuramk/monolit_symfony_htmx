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
        private LegacyProductText $text,
    ) {
    }

    public function get(): OrderFormDto {
        $groups = $this->products->findProductGroups();
        foreach ($groups as &$group) {
            $group['name'] = $this->text->format($group['name']);
        }
        unset($group);
        $products = $this->products->findProductsByParent();
        foreach ($products as &$variants) {
            foreach ($variants as &$variant) {
                $variant['name'] = $this->text->format($variant['name']);
                $variant['unit'] = $this->text->format($variant['unit'] ?? '');
            }
            unset($variant);
        }
        unset($variants);

        return new OrderFormDto(
            $groups,
            $products,
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
