<?php declare(strict_types=1);

namespace App\Domain\Order\Entities;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Customer\Entities\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\OrderVersionConflict;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Order\UseCases\Dtos\UpdateOrderDto;
use App\Domain\Order\ValueObjects\OrderItemData;
use App\Domain\Product\Entities\Product;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'IDX_ORDERS_MANAGER', columns: ['manager_id'])]
class Order {
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    private int $id;

    #[ORM\Column(name: 'manager_id', type: 'bigint', nullable: true)]
    private ?int $managerId = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer;

    #[ORM\Column(name: 'customer_name', length: 510)]
    private string $customerName;

    #[ORM\Column(name: 'customer_phone', length: 64, nullable: true)]
    private ?string $customerPhone;

    #[ORM\Column(name: 'shipping_address', type: 'text', nullable: true)]
    private ?string $shippingAddress;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comments;

    #[ORM\Column(name: 'desired_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $desiredAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 32, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::New;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $prepayment = '0.00';

    #[ORM\Column(name: 'shipping_cost', type: 'decimal', precision: 12, scale: 2)]
    private string $shippingCost;

    #[ORM\Column(name: 'carrier_id', type: 'bigint', nullable: true)]
    private ?int $carrierId = null;

    #[ORM\Column(name: 'shipping_trips', nullable: true)]
    private ?int $shippingTrips = null;

    #[ORM\Column(name: 'shipping_unit_price', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $shippingUnitPrice = null;

    #[ORM\Column(type: 'smallint')]
    private int $markup;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $loyalty = null;

    #[ORM\Column(name: 'loyalty_comment', type: 'text', nullable: true)]
    private ?string $loyaltyComment = null;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'edit_version')]
    private int $version = 0;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    /** @var Collection<int, OrderPayment> */
    #[ORM\OneToMany(targetEntity: OrderPayment::class, mappedBy: 'order', cascade: ['persist'])]
    private Collection $payments;

    /** @param list<OrderItemData> $items */
    public function __construct(int $id, Customer $customer, \DateTimeImmutable $desiredAt, ?string $shippingAddress, ?string $comments, Decimal $shippingCost, int $markup, array $items, \DateTimeImmutable $now) {
        $this->id = $id;
        $this->customer = $customer;
        $this->customerName = $customer->getName();
        $this->customerPhone = $customer->getPhone();
        $this->createdAt = $this->importedAt = $this->updatedAt = $now;
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->changeDetails($desiredAt, $shippingAddress, $comments, $shippingCost, $markup);
        $this->syncItems($items);
    }

    public static function create(int $id, Customer $customer, CreateOrderDto $data, \DateTimeImmutable $now, ?int $managerId = null): self {
        $items = [];
        foreach ($data->lines as $line) {
            $product = $data->products[$line->productId] ?? null;
            if (!$product instanceof Product || $product->getId() !== $line->productId || !$product->isAvailableForOrder(requireActiveParent: false)) {
                throw new \DomainException('Товар недоступен для добавления в заказ.');
            }
            $items[] = new OrderItemData(
                lineId: 0,
                product: $product,
                quantity: Decimal::fromString($line->quantity, 3),
                unitPrice: $line->unitPrice === null ? $product->getPrice() : Decimal::fromString($line->unitPrice, 4),
                discount: Decimal::fromString($line->discount, 2),
            );
        }

        $order = new self(
            id: $id,
            customer: $customer,
            desiredAt: $data->desiredAt,
            shippingAddress: $data->shippingAddress,
            comments: $data->comments,
            shippingCost: Decimal::fromString($data->shippingCost, 2),
            markup: $data->markup,
            items: $items,
            now: $now,
        );
        $order->managerId = $managerId;
        $order->carrierId = $data->carrierId;
        $order->shippingTrips = $data->shippingTrips;
        $order->shippingUnitPrice = $data->shippingUnitPrice;

        return $order;
    }

    public function hasPayments(): bool {
        if (Decimal::fromString($this->prepayment, 2)->scaledValue() > 0) {
            return true;
        }
        foreach ($this->payments as $payment) {
            if ($payment->isPositive()) {
                return true;
            }
        }

        return false;
    }

    public function getStatus(): OrderStatus {

        return $this->status;
    }

    public function assertVersion(int $version): void {
        if ($this->version !== $version) {
            throw new OrderVersionConflict('Заказ уже изменён. Обновите страницу.');
        }
    }

    public function shippingChanged(\DateTimeImmutable $now, ?OrderStatus $status = null): void {
        if ($status !== null) {
            $this->status = $status;
        }
        $this->updatedAt = $now;
        ++$this->version;
    }

    public function changeStatus(
        OrderStatus $status,
        int $expectedVersion,
        bool $main,
        bool $hasShipments,
        string $reason,
        \DateTimeImmutable $now,
    ): bool {
        if ($this->version !== $expectedVersion) {
            throw new OrderVersionConflict('Заказ уже изменён. Обновите страницу перед сохранением.');
        }
        if ($status === OrderStatus::PaidAndDelivered && !$main) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException(
                'Этот статус может установить только пользователь с ролью «Главная».',
            );
        }
        if ($this->status === $status) {

            return false;
        }
        if ($status === OrderStatus::Cancelled) {
            if ($this->hasPayments() || $hasShipments) {
                throw new \DomainException('Нельзя отменить заказ: уже существуют оплаты или отгрузки.');
            }
            if (trim($reason) === '') {
                throw new \DomainException('Укажите причину отмены заказа.');
            }
        }
        $this->status = $status;
        $this->updatedAt = $now;
        ++$this->version;

        return true;
    }

    public function addPayment(int $id, Decimal $amount, bool $toCard, int $userId, string $operationId, \DateTimeImmutable $now, bool $shipment = false): OrderPayment {
        if ($this->status === OrderStatus::Cancelled) throw new \DomainException('В отменённый заказ нельзя внести оплату.');
        if ($amount->scale() > 2 || $amount->isNegative() || $amount->isZero() || $amount->scaledValue() > 999999999999) {
            throw new \DomainException('Сумма оплаты должна быть положительной, не более 9999999999.99 грн и с точностью до копеек.');
        }
        if ($toCard && $this->markup !== 0) throw new \DomainException('Оплата на карту доступна только для заказа без БН/ФОП.');
        $paid = Decimal::fromString($this->prepayment, 2)->add($shipment ? Decimal::zero(2) : $amount);
        if ($paid->scaledValue() > 999999999999) throw new \DomainException('Общая сумма оплат превышает допустимое значение.');
        $payment = new OrderPayment($this, $id, $amount, $toCard, $this->markup, $userId, $operationId, $now, $shipment);
        $this->payments->add($payment);
        $this->prepayment = $paid->format(2);
        $this->updatedAt = $now;
        ++$this->version;
        return $payment;
    }

    public function assertEditable(int $expectedVersion, int $markup): void {
        if ($this->status === OrderStatus::Cancelled) {
            throw new \DomainException('Отменённый заказ нельзя редактировать.');
        }
        if ($this->version !== $expectedVersion) {
            throw new OrderVersionConflict('Заказ уже изменён. Обновите страницу перед сохранением.');
        }
        if ($this->markup !== $markup && $this->hasPayments()) {
            throw new \DomainException('Нельзя менять БН: по заказу уже существуют оплаты.');
        }
    }

    public function update(UpdateOrderDto $data, \DateTimeImmutable $now): bool {
        $this->assertEditable($data->version, $data->markup);
        $items = [];
        foreach ($data->lines as $line) {
            $existing = $line->lineId === 0 ? null : $this->getItem($line->lineId);
            if ($line->lineId !== 0 && $existing === null) {
                throw new \DomainException('Позиция не принадлежит заказу.');
            }
            if ($existing !== null && (int) $existing->getProductId() === $line->productId) {
                $product = $existing->getProduct();
                $snapshot = $existing->getProductSnapshot();
            } else {
                $product = $data->products[$line->productId] ?? null;
                if (!$product instanceof Product || $product->getId() !== $line->productId || !$product->isAvailableForOrder()) {
                    throw new \DomainException('Товар недоступен для добавления в заказ.');
                }
                $snapshot = null;
            }
            $items[] = new OrderItemData(
                lineId: $line->lineId,
                product: $product,
                quantity: Decimal::fromString($line->quantity, 3),
                unitPrice: Decimal::fromString($line->unitPrice, 4),
                discount: Decimal::fromString($line->discount, 2),
                productSnapshot: $snapshot,
            );
        }

        return $this->revise(
            expectedVersion: $data->version,
            desiredAt: $data->desiredAt,
            shippingAddress: $data->shippingAddress,
            comments: $data->comments,
            shippingCost: Decimal::fromString($data->shippingCost, 2),
            markup: $data->markup,
            items: $items,
            now: $now,
        );
    }

    /** @param list<OrderItemData> $items */
    public function revise(int $expectedVersion, \DateTimeImmutable $desiredAt, ?string $shippingAddress, ?string $comments, Decimal $shippingCost, int $markup, array $items, \DateTimeImmutable $now): bool {
        $this->assertEditable($expectedVersion, $markup);
        if ($markup < 0 || $markup > 20 || $shippingCost->isNegative()) {
            throw new \DomainException('Некорректная наценка или стоимость доставки.');
        }
        $changed = $this->syncItems($items);
        $changed = $this->changeDetails($desiredAt, $shippingAddress, $comments, $shippingCost, $markup) || $changed;
        if ($changed) {
            ++$this->version;
            $this->updatedAt = $now;
        }

        return $changed;
    }

    private function changeDetails(\DateTimeImmutable $desiredAt, ?string $address, ?string $comments, Decimal $cost, int $markup): bool {
        if ($markup < 0 || $markup > 20 || $cost->isNegative()) {
            throw new \DomainException('Некорректная наценка или стоимость доставки.');
        }
        $values = [$desiredAt->format('Y-m-d H:i:s'), self::nullable($address), self::nullable($comments), $cost->toScale(2)->format(), $markup];
        $old = isset($this->desiredAt) ? [$this->desiredAt->format('Y-m-d H:i:s'), $this->shippingAddress, $this->comments, $this->shippingCost, $this->markup] : null;
        if ($old === $values) {
            return false;
        }
        [, $this->shippingAddress, $this->comments, $this->shippingCost, $this->markup] = $values;
        $this->desiredAt = $desiredAt;

        return true;
    }

    /** @param list<OrderItemData> $data */
    private function syncItems(array $data): bool {
        if ($data === []) {
            throw new \DomainException('В заказе должна быть хотя бы одна позиция.');
        }
        $seen = [];
        foreach ($data as $line) {
            if ($line->lineId !== 0 && (isset($seen[$line->lineId]) || $this->getItem($line->lineId) === null)) {
                throw new \DomainException('Позиция не принадлежит заказу или указана повторно.');
            }
            $seen[$line->lineId] = true;
        }
        $changed = false;
        foreach ($this->items->toArray() as $item) {
            if ($item->getId() === null || !isset($seen[$item->getId()])) {
                $this->items->removeElement($item);
                $changed = true;
            }
        }
        foreach ($data as $index => $line) {
            $item = $line->lineId === 0 ? null : $this->getItem($line->lineId);
            if ($item === null) {
                $this->items->add(new OrderItem($this, $line, $index + 1));
                $changed = true;
            } else {
                $changed = $item->revise($line, $index + 1) || $changed;
            }
        }

        return $changed;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getCustomer(): ?Customer {
        return $this->customer;
    }

    public function getItem(int $id): ?OrderItem {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<OrderItem> */
    public function getItems(): array {
        $items = array_values($this->items->toArray());
        usort($items, static fn (OrderItem $left, OrderItem $right): int => $left->getPosition() <=> $right->getPosition());

        return $items;
    }

    private static function nullable(?string $value): ?string {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
