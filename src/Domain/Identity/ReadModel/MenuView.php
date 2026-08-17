<?php

declare(strict_types=1);

namespace App\Domain\Identity\ReadModel;

use App\Domain\Identity\ReadModel\Dtos\MenuItemDto;
use App\Domain\Identity\Repository\IdentityMenuRepository;

final readonly class MenuView {
    public function __construct(private IdentityMenuRepository $identityMenuRepository, private string $legacyAdminUrl) {
    }

    /** @return list<MenuItemDto> */
    public function getForUserId(int $userId): array {
        return $this->identityMenuRepository->getForUserId($userId, $this->legacyAdminUrl);
    }
}
