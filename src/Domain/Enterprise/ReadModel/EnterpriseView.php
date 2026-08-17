<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ReadModel;

use App\Domain\Enterprise\ReadModel\Dtos\EnterpriseViewDto;
use App\Domain\Enterprise\Repository\EnterpriseViewRepository;

final readonly class EnterpriseView {
    public function __construct(private EnterpriseViewRepository $enterpriseViewRepository) {
    }

    public function getById(int $id): EnterpriseViewDto {
        return $this->enterpriseViewRepository->getById($id);
    }
}
