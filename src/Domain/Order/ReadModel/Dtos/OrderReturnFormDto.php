<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

final readonly class OrderReturnFormDto {
    public function __construct(
        public array $order,
        public array $items,
        public ?array $draft
    ) {}
}
