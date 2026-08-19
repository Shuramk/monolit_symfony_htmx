<?php declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\Entities\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ObjectRepository;

final readonly class ProductRepository {
    private ObjectRepository $repository;
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(Product::class);
    }

    public function getById(int $id): Product {
        $product = $this->getByIds([$id])[$id] ?? null;
        if ($product === null) {
            throw new \DomainException('Товар не найден.');
        }

        return $product;
    }

    public function transactional(callable $operation): mixed {
        try {
            return $this->entityManager->getConnection()->transactional($operation);
        } catch (\Throwable $exception) {
            $this->entityManager->clear();
            throw $exception;
        }
    }

    public function findForUpdate(int $id): ?Product {
        return $this->entityManager->createQuery('SELECT p FROM App\Domain\Product\Entities\Product p WHERE p.id = :id')
            ->setParameter('id', $id)->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
    }

    public function nextId(): int {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('UPDATE product_number_sequence SET last_id = LAST_INSERT_ID(last_id + 1) WHERE id = 1');
        return (int) $connection->fetchOne('SELECT LAST_INSERT_ID()');
    }

    public function save(Product $product): void {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Продукция сохраняется только в транзакции.');
        }
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /** @param list<Product> $products */
    public function saveAll(array $products): void {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Продукция сохраняется только в транзакции.');
        }
        foreach ($products as $product) {
            $this->entityManager->persist($product);
        }
        $this->entityManager->flush();
    }

    /** @return array<int, Product> */
    public function findVariantsForUpdate(Product $group): array {
        $products = $this->entityManager->createQuery('SELECT p FROM App\Domain\Product\Entities\Product p WHERE p.parent = :parent ORDER BY p.id')
            ->setParameter('parent', $group)->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)->getResult();
        $result = [];
        foreach ($products as $product) {
            $result[$product->getId()] = $product;
        }
        return $result;
    }

    /** @param list<int> $ids
     * @return array<int, Product>
     */
    public function findGroupsForUpdate(array $ids): array {
        if ($ids === []) {
            return [];
        }
        $products = $this->entityManager->createQuery('SELECT p FROM App\Domain\Product\Entities\Product p WHERE p.id IN (:ids) AND p.parent IS NULL ORDER BY p.id')
            ->setParameter('ids', $ids)->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)->getResult();
        $result = [];
        foreach ($products as $product) {
            $result[$product->getId()] = $product;
        }
        return $result;
    }

    public function inheritGroupSettings(Product $group, \DateTimeImmutable $now): void {
        $variants = $this->entityManager->createQuery('SELECT p FROM App\Domain\Product\Entities\Product p WHERE p.parent = :parent ORDER BY p.id')
            ->setParameter('parent', $group)->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)->getResult();
        foreach ($variants as $variant) {
            $variant->inheritGroupSettings($now);
        }
    }

    public function recordChange(Product $product, int $userId, ?array $before, \DateTimeImmutable $now): void {
        $this->entityManager->getConnection()->insert('product_changelog', [
            'product_id' => $product->getId(), 'user_id' => $userId, 'event' => $before === null ? 'created' : 'updated',
            'before_snapshot' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'after_snapshot' => json_encode($product->getSettings(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    /** @param list<int> $ids
     * @return array<int, Product>
     */
    public function getByIds(array $ids): array {
        if ($ids === []) {
            return [];
        }

        $products = $this->repository->createQueryBuilder('product')
            ->select('product', 'parent')
            ->leftJoin('product.parent', 'parent')
            ->where('product.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->andWhere('product.enabled = true')
            ->andWhere('parent.enabled = true')
            ->setHint(Query::HINT_REFRESH, true)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($products as $product) {
            $result[$product->getId()] = $product;
        }

        return $result;
    }
}
