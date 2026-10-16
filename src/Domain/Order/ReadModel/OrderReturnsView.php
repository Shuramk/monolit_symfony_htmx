<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\Repository\OrderReturnRepository;
use App\Domain\Order\ReadModel\Dtos\OrderReturnsPageDto;
use App\Domain\Order\ReadModel\Dtos\OrderReturnFormDto;

final readonly class OrderReturnsView {
    public function __construct(private OrderReturnRepository $repository) {}

    public function page(bool $archive, int $page, bool $showDrafts, bool $showReturns, bool $createDraft): OrderReturnsPageDto {
        $pages = max(1, (int) ceil(
            max($showDrafts ? $this->repository->draftCount($archive) : 0, $showReturns && !$archive ? $this->repository->pendingCount() : 0) / 50)
        );
        $page = max(1, min($pages, $page));

        return new OrderReturnsPageDto(
            $showDrafts ? $this->repository->drafts($archive, $page) : [],
            $showReturns && !$archive ? $this->repository->returns(null, $page) : [],
            $createDraft && !$archive ? $this->repository->products() : [],
            $page,
            $pages,
            $archive
        );
    }

    public function forOrder(int $id): array {
        return $this->repository->returns($id);
    }

    public function operationId(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' .
            substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    public function form(int $id, ?int $draftId): OrderReturnFormDto {
        $order = $this->repository->order($id);
        if ($order['status'] === 'cancelled') {
            throw new \DomainException('Нельзя принять возврат по отменённому заказу.');
        }

        $draft = $draftId === null ? null : $this->repository->draft($draftId);
        if ($draft && $draft['done']) {
            throw new \DomainException('Заявка уже принята.');
        }

        $prefill = [];
        foreach ($draft['items'] ?? [] as $row) {
            $prefill[(int)$row['product_id']] = ($prefill[(int)$row['product_id']] ?? Decimal::zero(3))->add(Decimal::fromString($row['quantity'], 3));
        }
        $shipped = $this->repository->shipped($id);
        $pending = $this->repository->pending($id);
        $items = [];
        foreach ($order['items'] as $line) {
            $product = (int) $line['product_id'];
            if ($product === 0) continue;
            if (isset($items[$product])) {
                if ($items[$product]['unit_price'] !== $line['unit_price']) $items[$product]['invalidPrice'] = true;
                continue;
            }
            $items[$product] = $line + [
                    'productId' => $product,
                    'invalidPrice' => false,
                    'quantityInput' => isset($prefill[$product]) ? $prefill[$product]->format(3) : '',
                    'available' => Decimal::fromString((string) ($shipped[$product] ?? '0'), 3)
                        ->subtract(Decimal::fromString((string) ($pending[$product] ?? '0'), 3))
                        ->format(3)
                ];
        }

        return new OrderReturnFormDto($order, array_values($items), $draft);
    }
}
