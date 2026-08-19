<?php declare(strict_types=1);

namespace App\Domain\Order\ValueObjects;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Entities\Product;

final readonly class OrderItemData {
    public Decimal $quantity;
    /** @var array{name: string, group_name: ?string, unit: string} */
    public array $productSnapshot;

    /** @param array{name: string, group_name: ?string, unit: string}|null $productSnapshot */
    public function __construct(
        public int $lineId,
        public ?Product $product,
        Decimal $quantity,
        public Decimal $unitPrice,
        public Decimal $discount,
        ?array $productSnapshot = null,
    ) {
        if ($productSnapshot === null && $product === null) {
            throw new \DomainException('Для позиции необходим товар или его исторический снимок.');
        }
        $this->productSnapshot = $productSnapshot ?? [
            'name' => $product->getName(),
            'group_name' => $product->getParent()?->getName(),
            'unit' => $product->getUnit(),
        ];
        $this->quantity = $quantity = $quantity->toScale(3);
        if ($quantity->isZero() || $quantity->isNegative()) {
            throw new \DomainException('Количество товара должно быть больше нуля.');
        }
        if ($unitPrice->isNegative()) {
            throw new \DomainException('Цена товара не может быть отрицательной.');
        }
        $discountValue = $discount->toScale(2)->scaledValue();
        if ($discountValue < -10000 || $discountValue > 10000) {
            throw new \DomainException('Скидка должна быть от -100 до 100 процентов.');
        }
    }
}
