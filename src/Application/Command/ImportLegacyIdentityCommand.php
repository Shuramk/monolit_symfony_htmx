<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Identity\Import\LegacyPermissionExpander;
use App\Domain\Identity\Import\LegacyUserRoleMapper;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: "legacy:import-identity", description: "Imports all users, permissions, and menu metadata from the read-only legacy database.")]
final class ImportLegacyIdentityCommand extends Command {
    public function __construct(
        #[Autowire("@doctrine.dbal.legacy_connection")]
        private readonly Connection $legacy,
        #[Autowire("@doctrine.dbal.app_connection")]
        private readonly Connection $app,
        private readonly LegacyPermissionExpander $permissionExpander,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addOption('start-pages-only',null,InputOption::VALUE_NONE,'Imports only legacy start pages without replacing users, permissions or sessions.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if ($input->getOption('start-pages-only')) {
            $this->app->transactional(function (): void {
                foreach ($this->legacy->fetchAllAssociative('SELECT userID,didDefault FROM sklad_user_permissions') as $permission) $this->importStartPage($permission);
            });
            (new SymfonyStyle($input,$output))->success('Imported legacy start pages only.');
            return Command::SUCCESS;
        }
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");
        $users = $this->usersToImport();
        $permissions = $this->legacy->fetchAllAssociative("SELECT userID, userPermissions, didDefault FROM sklad_user_permissions");
        $divisions = $this->legacy->fetchAllAssociative("SELECT xID, xParentID, xSubParentID, xName, xUnicKey, xEnabled, xPriority FROM sklad_divisions");

        $this->app->transactional(function () use ($now, $users, $permissions, $divisions): void {
            $this->app->executeStatement("DELETE FROM identity_user_sessions");
            $this->app->executeStatement("DELETE FROM identity_user_permissions");
            $this->app->executeStatement("DELETE FROM identity_users");
            $this->app->executeStatement("DELETE FROM identity_groups");

            foreach ($divisions as $division) {
                $this->importDivision($division);
            }

            foreach ($users as $user) {
                $this->importUser($user, $now);
            }

            $importedUserIds = array_column($users, "user_id");

            foreach ($permissions as $permission) {
                if (!in_array($permission["userID"], $importedUserIds, true)) {
                    continue;
                }

                $this->importUserPermissions($permission);
                $this->importStartPage($permission);
            }
        });

        (new SymfonyStyle($input, $output))->success(sprintf("Imported %d users and %d legacy divisions.", count($users), count($divisions)));

        return Command::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function usersToImport(): array {
        $rows = $this->legacy->fetchAllAssociative("SELECT user_id, user_login, user_name, groupID, user_priv, user_password, password_hash, password_hash_updated_at FROM sklad_users WHERE user_login IS NOT NULL AND TRIM(user_login) <> '' ORDER BY user_id DESC");
        $users = [];

        foreach ($rows as $row) {
            $login = mb_strtolower(trim((string) $row["user_login"]));

            if (isset($users[$login])) {
                continue;
            }

            $users[$login] = $row;
        }

        return array_values($users);
    }

    /** @param array<string, mixed> $division */
    private function importDivision(array $division): void {
        $this->app->executeStatement("INSERT INTO identity_permissions (code, title, legacy_division_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title)", ["legacy.did.".$division["xID"], $division["xName"], $division["xID"]]);
        $this->app->executeStatement("INSERT INTO identity_legacy_divisions (id, parent_id, sub_parent_id, title, module_key, enabled, priority) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), sub_parent_id=VALUES(sub_parent_id), title=VALUES(title), module_key=VALUES(module_key), enabled=VALUES(enabled), priority=VALUES(priority)", [(int) $division["xID"], (int) $division["xParentID"], (int) $division["xSubParentID"], $division["xName"], $division["xUnicKey"], (int) $division["xEnabled"], (int) $division["xPriority"]]);
    }

    /** @param array<string, mixed> $user */
    private function importUser(array $user, string $now): void {
        $groupId = (int) $user["groupID"];
        $enabled = $user["user_password"] !== "*" && $user["user_password"] !== "";
        $login = trim((string) ($user["user_login"] ?? ""));


        $this->app->executeStatement("INSERT INTO identity_groups (legacy_group_id, code, name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)", [$groupId, "legacy-group-".$groupId, "Legacy group ".$groupId]);
        $this->app->executeStatement("INSERT INTO identity_users (id, login, display_name, role, legacy_group_id, legacy_user_priv, enabled, password_hash, password_hash_updated_at, imported_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE login=VALUES(login), display_name=VALUES(display_name), role=VALUES(role), legacy_group_id=VALUES(legacy_group_id), legacy_user_priv=VALUES(legacy_user_priv), enabled=VALUES(enabled), password_hash=VALUES(password_hash), password_hash_updated_at=VALUES(password_hash_updated_at), updated_at=VALUES(updated_at)", [(int) $user["user_id"], $login, $user["user_name"], LegacyUserRoleMapper::fromGroupId($groupId)?->value, $groupId, (int) $user["user_priv"], $enabled ? 1 : 0, $user["password_hash"], $user["password_hash_updated_at"], $now, $now]);
    }

    /** @param array{userID: int|string, userPermissions: ?string} $permission */
    private function importUserPermissions(array $permission): void {
        foreach ($this->permissionExpander->expand($permission["userPermissions"]) as $divisionId) {
            $permissionId = $this->app->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = ?", [$divisionId]);

            if ($permissionId !== false) {
                $this->app->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [(int) $permission["userID"], $permissionId]);
            }
        }
    }

    private function importStartPage(array $permission): void {
        $value = trim(explode(';',(string)($permission['didDefault'] ?? ''))[0]);
        $id = ctype_digit($value) ? (int)$value : null;
        if ($id !== null && !$this->app->fetchOne('SELECT 1 FROM identity_user_permissions up JOIN identity_permissions p ON p.id=up.permission_id JOIN identity_legacy_divisions d ON d.id=p.legacy_division_id WHERE up.user_id=? AND d.id=? AND d.enabled=1',[(int)$permission['userID'],$id])) $id = null;
        $this->app->update('identity_users',['start_page_id'=>$id],['id'=>(int)$permission['userID']]);
    }
}
