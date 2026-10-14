<?php declare(strict_types=1);

namespace App\Domain\Identity\Import;

use App\Domain\Identity\Enums\UserRole;

final class LegacyUserRoleMapper {
    public static function fromGroupId(int $id): ?UserRole {
        return match ($id) {
            1 => UserRole::Main,
            2 => UserRole::Sales,
            3 => UserRole::Production,
            10 => UserRole::ShiftSupervisor,
            99 => UserRole::Partner,
            default => null,
        };
    }
}
