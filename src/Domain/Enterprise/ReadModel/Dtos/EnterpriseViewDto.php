<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ReadModel\Dtos;

final readonly class EnterpriseViewDto {
    /** @param list<array{id: int, kind: string, number: string}> $phones */
    public function __construct(
        public int $id,
        public ?string $displayName,
        public ?string $shortName,
        public ?string $edrpouVat,
        public ?string $edrpouPe,
        public ?string $address,
        public ?string $email,
        public array $phones,
        public ?string $website = null,
    ) {
    }
}
