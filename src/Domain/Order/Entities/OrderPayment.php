<?php

declare(strict_types=1);

namespace App\Domain\Order\Entities;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\Enums\PaymentMoneyStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'order_payments')]
#[ORM\Index(name: 'IDX_ORDER_PAYMENTS_ORDER', columns: ['order_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_ORDER_PAYMENT_OPERATION', columns: ['operation_id'])]
class OrderPayment {
    #[ORM\Id]
    #[ORM\Column(length: 24)]
    private string $source;

    #[ORM\Id]
    #[ORM\Column(name: 'source_id', type: 'bigint')]
    private int $sourceId;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(name: 'paid_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $paidAt;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment;

    #[ORM\Column(name: 'user_id', type: 'bigint', nullable: true)]
    private ?int $userId;

    #[ORM\Column(name: 'shipment_id', type: 'bigint', nullable: true)]
    private ?int $shipmentId;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(name: 'money_taken', type: 'smallint', nullable: true, enumType: PaymentMoneyStatus::class)]
    private ?PaymentMoneyStatus $moneyTaken = null;

    #[ORM\Column(name: 'to_card', type: 'boolean', nullable: true)]
    private ?bool $toCard = null;

    #[ORM\Column(name: 'payment_markup', type: 'smallint', nullable: true)]
    private ?int $paymentMarkup = null;

    #[ORM\Column(name: 'metadata_imported_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $metadataImportedAt = null;

    #[ORM\Column(name: 'operation_id', length: 36, nullable: true)]
    private ?string $operationId = null;

    public function __construct(Order $order, int $id, Decimal $amount, bool $toCard, int $markup, int $userId, string $operationId, \DateTimeImmutable $now, bool $shipment = false) {
        $this->source = $shipment ? 'application_shipment' : 'application';
        $this->sourceId = $id;
        $this->order = $order;
        $this->amount = $amount->format(2);
        $this->toCard = $toCard;
        $this->paymentMarkup = $markup;
        $this->moneyTaken = PaymentMoneyStatus::Pending;
        $this->userId = $userId;
        $this->operationId = $operationId;
        $this->paidAt = $this->importedAt = $this->metadataImportedAt = $now;
        $this->comment = null;
        $this->shipmentId = null;
    }

    public function matches(int $orderId, Decimal $amount, bool $toCard, int $userId, bool $shipment = false): bool {
        return $this->order->getId() === $orderId && Decimal::fromString($this->amount, 2)->equals($amount)
            && $this->toCard === $toCard && $this->userId === $userId && $this->isShipment() === $shipment;
    }

    public function getMessage(): string {
        return ($this->isShipment() ? 'Внесена оплата за доставку ' : 'Внесена оплата ').$this->amount.' грн'.($this->paymentMarkup > 0 ? ' (БН)' : '').($this->toCard ? ' (внесено на карту)' : '');
    }

    public function isShipment(): bool { return in_array($this->source, ['shipment', 'application_shipment'], true); }

    public function getId(): int { return $this->sourceId; }

    public function isPositive(): bool {
        return Decimal::fromString($this->amount, 2)->scaledValue() > 0;
    }
}
