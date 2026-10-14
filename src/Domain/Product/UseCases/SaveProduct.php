<?php declare(strict_types=1);

namespace App\Domain\Product\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Entities\Product;
use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Product\UseCases\Dtos\SaveProductDto;

final readonly class SaveProduct {
    public function __construct(private ProductRepository $repository) {}

    public function execute(SaveProductDto $data, int $userId, ?int $id = null, ?int $parentId = null): int {
        return $this->repository->transactional(function () use ($data, $userId, $id, $parentId): int {
            $now = new \DateTimeImmutable();
            $before = null;
            if ($id !== null) {
                $product = $this->repository->findForUpdate($id) ?? throw new \DomainException('Продукция не найдена.');
                $before = $product->getSettings();
            } else {
                $parent = $parentId === null ? null : ($this->repository->findForUpdate($parentId) ?? throw new \DomainException('Группа не найдена.'));
                if ($parent?->getParent() !== null) {
                    throw new \DomainException('Цвет можно добавить только в группу.');
                }
                $product = new Product($this->repository->nextId(), $data->name, $parent?->getUnit() ?? $data->unit,
                    $parent === null ? Decimal::zero(4) : Decimal::fromString($data->price, 4), $now, $parent);
            }
            $changed = $product->updateSettings($data, $now);
            if ($before === null || $changed) {
                if ($product->getParent() === null) {
                    $this->repository->inheritGroupSettings($product, $now);
                }
                $this->repository->save($product);
                $this->repository->recordChange($product, $userId, $before, $now);
            }
            return $product->getId();
        });
    }
}
