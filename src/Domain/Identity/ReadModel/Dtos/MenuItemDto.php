<?php declare(strict_types=1);

namespace App\Domain\Identity\ReadModel\Dtos;

final readonly class MenuItemDto {
    /** @param list<MenuItemDto> $children */
    public function __construct(
        public int $divisionId,
        public string $title,
        public string $url,
        public array $children,
    ) {
    }
}
