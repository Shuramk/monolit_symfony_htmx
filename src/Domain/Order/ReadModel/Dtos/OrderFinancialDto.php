<?php

declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

final readonly class OrderFinancialDto
{
    /** @param list<array{unitPrice: int, total: int}> $lines */
    public function __construct(
        public int $goodsTotal,
        public int $total,
        public int $paidAmount,
        public bool $fullyPaid,
        public array $lines,
    ) {
    }
}
