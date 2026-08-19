<?php declare(strict_types=1);

namespace App\Domain\Product\ReadModel;

use App\Domain\Product\ReadModel\Dtos\ProductSettingsPageDto;
use App\Domain\Product\ReadModel\Dtos\ProductSettingsEditDto;
use App\Domain\Product\Repository\ProductSettingsRepository;

final readonly class ProductSettingsView {
    public function __construct(private ProductSettingsRepository $repository) {}

    public function page(string $query, bool $includeDisabled): ProductSettingsPageDto {
        return new ProductSettingsPageDto($this->repository->groups($query, $includeDisabled), $query, $includeDisabled);
    }

    public function edit(int $id): ?ProductSettingsEditDto {
        $product = $this->repository->find($id);
        if ($product === null) {
            return null;
        }
        $parent = $product['parent_id'] === null ? null : $this->repository->find((int) $product['parent_id']);
        return new ProductSettingsEditDto($product, $parent, $parent === null ? $this->repository->variants($id) : [], $this->repository->history($id));
    }

    public function new(?int $parentId): ?ProductSettingsEditDto {
        $parent = $parentId === null ? null : $this->repository->find($parentId);
        if ($parentId !== null && ($parent === null || $parent['parent_id'] !== null)) {
            return null;
        }
        return new ProductSettingsEditDto(null, $parent, [], []);
    }
}
