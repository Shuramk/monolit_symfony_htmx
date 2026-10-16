<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel\Dtos;

final readonly class OrderReturnsPageDto {
    public function __construct(
        public array $drafts,
        public array $returns,
        public array $products,
        public int $page,
        public int $pages,
        public bool $archive
    ) {}
}
