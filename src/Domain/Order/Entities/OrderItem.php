<?php declare(strict_types=1);

namespace App\Domain\Order\Entities;

use App\Domain\Order\ValueObjects\OrderItemData;
use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Entities\Product;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
#[ORM\UniqueConstraint(name: 'UNIQ_ORDER_ITEM_POSITION', columns: ['order_id', 'position'])]
class OrderItem {
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $product;

    #[ORM\Column(name: 'product_name', length: 510)]
    private string $productName;

    #[ORM\Column(name: 'product_group_name', length: 510, nullable: true)]
    private ?string $productGroupName;

    #[ORM\Column(length: 64)]
    private string $unit;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $quantity;

    #[ORM\Column(name: 'unit_price', type: 'decimal', precision: 12, scale: 4)]
    private string $unitPrice;

    #[ORM\Column]
    private int $position;

    public function __construct(Order $order, OrderItemData $data, int $position) {
        $this->order = $order;
        $this->revise($data, $position);
    }

    public function revise(OrderItemData $data, int $position): bool {
        $productId = $data->product?->getId();
        $price = $data->unitPrice->toScale(4)->multiplyByInt(10000 - $data->discount->toScale(2)->scaledValue())->divideByInt(10000);
        $values = [$productId, $data->productSnapshot['name'], $data->productSnapshot['group_name'], $data->productSnapshot['unit'], $data->quantity->format(), $price->format(), $position];
        $old = isset($this->position) ? [$this->getProductId(), $this->productName, $this->productGroupName, $this->unit, $this->quantity, $this->unitPrice, $this->position] : null;
        [, $this->productName, $this->productGroupName, $this->unit, $this->quantity, $this->unitPrice, $this->position] = $values;
        $this->product = $data->product;

        return $old !== $values;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getPosition(): int {
        return $this->position;
    }

    public function getProductId(): ?int {
        return $this->product?->getId();
    }

    public function getProduct(): ?Product {
        return $this->product;
    }

    public function getQuantity(): Decimal {
        return Decimal::fromString($this->quantity, 3);
    }

    public function getUnitPrice(): Decimal {
        return Decimal::fromString($this->unitPrice, 4);
    }

    /** @return array{id: ?int, name: string, group_name: ?string, unit: string} */
    public function getProductSnapshot(): array {
        return ['id' => $this->getProductId(), 'name' => $this->productName, 'group_name' => $this->productGroupName, 'unit' => $this->unit];
    }
}
