<?php declare(strict_types=1);

namespace App\Domain\Product\UseCases;

use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Product\UseCases\Dtos\UpdateProductVariantsDto;

final readonly class UpdateProductVariants {
    public function __construct(private ProductRepository $repository) {}

    /** @return array<int, int> Updated versions, including unchanged submitted rows. */
    public function execute(int $groupId, UpdateProductVariantsDto $data, int $userId): array {
        return $this->repository->transactional(function () use ($groupId, $data, $userId): array {
            $group = $this->repository->findForUpdate($groupId) ?? throw new \DomainException('Группа не найдена.');
            if ($group->getParent() !== null) {
                throw new \DomainException('Выберите группу продукции.');
            }
            $products = $this->repository->findVariantsForUpdate($group);
            $now = new \DateTimeImmutable();
            $changed = $before = $versions = [];
            foreach ($data->rows as $row) {
                if (isset($versions[$row->id])) {
                    throw new \DomainException('Вариант указан дважды.');
                }
                $product = $products[$row->id] ?? throw new \DomainException('Вариант не принадлежит этой группе.');
                $snapshot = $product->getSettings();
                if ($product->updateVariant($row, $now)) {
                    $changed[] = $product;
                    $before[$row->id] = $snapshot;
                }
                $versions[$row->id] = $product->getEditVersion();
            }
            $this->repository->saveAll($changed);
            foreach ($changed as $product) {
                $this->repository->recordChange($product, $userId, $before[$product->getId()], $now);
            }
            return $versions;
        });
    }
}
