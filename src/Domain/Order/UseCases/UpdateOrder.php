<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\Services\OrderChangelog;
use App\Domain\Order\UseCases\Dtos\UpdateOrderDto;
use App\Domain\Product\Repository\ProductRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class UpdateOrder {
    public function __construct(
        private OrderRepository $repository,
        private OrderChangelog $changelog,
        private ProductRepository $products,
    ) {}

    public function execute(int $id, UpdateOrderDto $dto, int $userId, \DateTimeImmutable $now): void {
        $this->repository->transactional(function () use ($id, $dto, $userId, $now): void {
            $order = $this->repository->findForUpdate($id);
            if ($order === null) {
                throw new NotFoundHttpException('Заказ не найден.');
            }
            $order->assertEditable($dto->version, $dto->markup);
            $dto->products = $this->products->getByIds(array_column($dto->lines, 'productId'));
            $before = $this->changelog->snapshot($id);
            if ($order->update($dto, $now)) {
                $this->repository->save($order);
                $this->changelog->record($id, 'updated', 'Заказ редактировался', $userId, $now, ['before' => $before]);
            }
        });
    }
}
