<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ReadModel;

use App\Domain\Enterprise\ReadModel\Dtos\EnterpriseWarrantyViewDto;
use App\Domain\Enterprise\Repository\EnterpriseWarrantyRepository;

final readonly class EnterpriseWarrantyView {
    public function __construct(
        private EnterpriseWarrantyRepository $enterpriseWarrantyRepository,
        private EnterpriseWarrantyHtmlSanitizer $sanitizer,
    ) {
    }

    public function getByEnterpriseId(int $enterpriseId): EnterpriseWarrantyViewDto {
        return new EnterpriseWarrantyViewDto(
            $enterpriseId,
            $this->sanitizer->sanitize($this->enterpriseWarrantyRepository->getSourceHtmlByEnterpriseId($enterpriseId)),
        );
    }
}
