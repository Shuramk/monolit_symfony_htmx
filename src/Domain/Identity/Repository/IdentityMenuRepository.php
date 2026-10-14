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
            ->andWhere("EXISTS (SELECT 1 FROM identity_users u WHERE u.id = user_permission.user_id AND u.enabled = 1 AND u.role IN ('ROLE_MAIN','ROLE_SALES','ROLE_PRODUCTION','ROLE_SHIFT_SUPERVISOR','ROLE_PARTNER'))")
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
            "SELECT 1 FROM identity_user_permissions user_permission INNER JOIN identity_permissions permission ON permission.id = user_permission.permission_id INNER JOIN identity_users u ON u.id = user_permission.user_id WHERE user_permission.user_id = ? AND permission.legacy_division_id = ? AND u.enabled = 1 AND u.role IN ('ROLE_MAIN','ROLE_SALES','ROLE_PRODUCTION','ROLE_SHIFT_SUPERVISOR','ROLE_PARTNER')",
            [$userId, $divisionId],
        );
    }

    public function startPageUrl(int $userId, int $divisionId, string $legacyAdminUrl): ?string {
        if (!$this->userHasDivision($userId,$divisionId) || !$this->connection->fetchOne('SELECT 1 FROM identity_legacy_divisions WHERE id=? AND enabled=1',[$divisionId])) return null;
        $required = match ($divisionId) { 100=>101, 200=>220, 1000=>1010, default=>$divisionId };
        return $this->userHasDivision($userId,$required) ? $this->url($divisionId,$legacyAdminUrl) : $legacyAdminUrl.'?did='.$divisionId;
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
        $crm = match ($divisionId) {
            1699,1700=>'/crm/deals',1701=>'/crm/deals/new',
            1750=>'/crm/tasks',1751=>'/crm/tasks/new',1754=>'/crm/tasks/archive',
            1755=>'/crm/director/tasks',1756=>'/crm/director/tasks/new',1759=>'/crm/director/tasks/archive',
            default=>null,
        };
        if ($crm !== null) {

            return $crm;
        }
        if ($divisionId === 1001) return '/settings/users';
        if ($divisionId === 1030) {
            return "/enterprise";
        }

        if ($divisionId === 1000 || $divisionId === 1010) {
            return "/settings/products";
        }

        if ($divisionId === 100 || $divisionId === 101) {
            return "/warehouse/products";
        }

        if ($divisionId === 200 || $divisionId === 220) {
            return "/orders";
        }

        return $legacyAdminUrl."?did=".$divisionId;
    }
}
