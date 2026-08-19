<?php declare(strict_types=1);

namespace App\Domain\Product\UseCases;

use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Product\UseCases\Dtos\UpdateProductSortingDto;

final readonly class UpdateProductSorting {
    public function __construct(private ProductRepository $repository) {}

    public function execute(UpdateProductSortingDto $data, int $userId): array {
        return $this->repository->transactional(function () use ($data, $userId): array {
            $rows = $data->rows;
            usort($rows, static fn ($a, $b): int => $a->id <=> $b->id);
            $products = $this->repository->findGroupsForUpdate(array_map(static fn ($row): int => $row->id, $rows));
            $changed = $before = $versions = [];
            $now = new \DateTimeImmutable();
            foreach ($rows as $row) {
                if (isset($versions[$row->id])) {
                    throw new \DomainException('Группа указана дважды.');
                }
                $product = $products[$row->id] ?? throw new \DomainException('Группа не найдена.');
                $snapshot = $product->getSettings();
                if ($product->changeSorting($row->sorting, $row->version, $now)) {
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
