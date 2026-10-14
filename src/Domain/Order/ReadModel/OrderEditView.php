<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\Dtos\OrderEditViewDto;
use App\Domain\Order\Repository\OrderViewRepository;

final readonly class OrderEditView {
    public function __construct(private OrderViewRepository $repository, private OrderFormView $formView) {}

    public function find(int $id): ?OrderEditViewDto {
        $order = $this->repository->findForEdit($id);
        if ($order === null) {
            return null;
        }
        $form = $this->formView->get();
        $groups = $form->productGroups;
        $groupIds = array_column($groups, 'id');
        $productsByParent = array_intersect_key($form->productsByParent, array_fill_keys($groupIds, true));
        $lines = [];
        foreach ($order['lines'] as $line) {
            $groupId = $line['parent_id'] === null ? -(int) $line['id'] : (int) $line['parent_id'];
            if (!in_array($groupId, $groupIds)) {
                $groups[] = ['id' => $groupId, 'name' => $line['product_group_name'] ?: 'Товар из старого заказа'];
                $groupIds[] = $groupId;
            }
            $options = $productsByParent[$groupId] ?? [];
            $productId = (int) ($line['product_id'] ?? 0);
            $options = array_values(array_filter($options, static fn (array $product): bool => (int) $product['id'] !== $productId));
            array_unshift($options, ['id' => $productId, 'name' => $line['product_name'], 'price' => $line['unit_price']]);
            $lines[] = [
                'lineId' => (int) $line['id'], 'productId' => $productId, 'groupId' => $groupId,
                'quantity' => $line['quantity'], 'unitPrice' => $line['unit_price'], 'options' => $options,
            ];
        }

        return new OrderEditViewDto($order, $lines, $groups, $productsByParent);
    }
}
