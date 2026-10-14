<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

final readonly class OrderSearchDto {
    public function __construct(
        public string $orderId = '',
        public string $customer = '',
        public string $phone = '',
        public string $address = '',
        public int $parentId = 0,
        public int $productId = 0,
        public string $quantity = '',
    ) {
        if ($quantity !== '' && preg_match('/^\d{1,10}(?:\.\d{1,3})?$/D', $quantity) !== 1) {
            throw new \DomainException('Количество должно быть положительным числом, не более трёх знаков после запятой.');
        }
    }

    public static function fromArray(array $data): self {
        foreach ($data as $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new \DomainException('Некорректное значение фильтра поиска.');
            }
        }

        return new self(
            orderId: trim((string) ($data['orderId'] ?? '')),
            customer: trim((string) ($data['customer'] ?? '')),
            phone: trim((string) ($data['phone'] ?? '')),
            address: trim((string) ($data['address'] ?? '')),
            parentId: max(0, (int) ($data['parentId'] ?? 0)),
            productId: max(0, (int) ($data['productId'] ?? 0)),
            quantity: str_replace(',', '.', trim((string) ($data['quantity'] ?? ''))),
        );
    }
}
