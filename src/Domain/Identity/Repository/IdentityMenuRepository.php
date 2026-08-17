<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repository;

use App\Domain\Identity\ReadModel\Dtos\MenuItemDto;
use Doctrine\DBAL\Connection;

final readonly class IdentityMenuRepository {
    public function __construct(private Connection $connection) {
    }

    /** @return list<MenuItemDto> */
    public function getForUserId(int $userId, string $legacyAdminUrl): array {
        $rows = $this->connection->createQueryBuilder()
            ->select("division.id", "division.parent_id", "division.title")
            ->from("identity_legacy_divisions", "division")
            ->innerJoin("division", "identity_permissions", "permission", "permission.legacy_division_id = division.id")
            ->innerJoin("permission", "identity_user_permissions", "user_permission", "user_permission.permission_id = permission.id")
            ->where("user_permission.user_id = :userId")
            ->andWhere("division.enabled = 1")
            ->setParameter("userId", $userId)
            ->orderBy("division.priority")
            ->executeQuery()
            ->fetchAllAssociative();

        $menu = [];

        foreach ($rows as $row) {
            if ((int) $row["parent_id"] !== 0) {
                continue;
            }

            $menu[] = new MenuItemDto(
                (int) $row["id"],
                $row["title"],
                $this->url((int) $row["id"], $legacyAdminUrl),
                $this->children($rows, (int) $row["id"], $legacyAdminUrl),
            );
        }

        return $menu;
    }

    public function userHasDivision(int $userId, int $divisionId): bool {
        return (bool) $this->connection->fetchOne(
            "SELECT 1 FROM identity_user_permissions user_permission INNER JOIN identity_permissions permission ON permission.id = user_permission.permission_id WHERE user_permission.user_id = ? AND permission.legacy_division_id = ?",
            [$userId, $divisionId],
        );
    }

    /** @param list<array{id: int|string, parent_id: int|string, title: string}> $rows
     * @return list<MenuItemDto>
     */
    private function children(array $rows, int $parentId, string $legacyAdminUrl): array {
        $children = [];

        foreach ($rows as $row) {
            if ((int) $row["parent_id"] !== $parentId) {
                continue;
            }

            $children[] = new MenuItemDto(
                (int) $row["id"],
                $row["title"],
                $this->url((int) $row["id"], $legacyAdminUrl),
                [],
            );
        }

        return $children;
    }

    private function url(int $divisionId, string $legacyAdminUrl): string {
        if ($divisionId === 1030) {
            return "/enterprise";
        }

        if ($divisionId === 1010) {
            return "/products";
        }

        return $legacyAdminUrl."?did=".$divisionId;
    }
}
