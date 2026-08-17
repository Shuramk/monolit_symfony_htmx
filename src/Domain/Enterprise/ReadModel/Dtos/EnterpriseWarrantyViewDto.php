<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ReadModel\Dtos;

final readonly class EnterpriseWarrantyViewDto {
    public function __construct(
        public int $enterpriseId,
        public string $html,
    ) {
    }
}
