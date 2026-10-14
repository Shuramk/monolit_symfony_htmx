<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

use App\Domain\Enterprise\ReadModel\Dtos\EnterpriseViewDto;

final readonly class OrderPrintViewDto {
    /** @param list<array{name: string, unit: string, quantity: string, unitPrice: string, total: string}> $lines */
    public function __construct(
        public int $id,
        public string $createdDate,
        public string $desiredDate,
        public string $customerName,
        public string $customerPhone,
        public string $shippingAddress,
        public string $managerName,
        public EnterpriseViewDto $enterprise,
        public array $lines,
        public string $subtotal,
        public ?string $vat,
        public string $total,
        public string $paid,
        public string $remaining,
    ) {
    }
}
