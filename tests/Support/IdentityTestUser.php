<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Identity\Entities\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait IdentityTestUser {
    protected function authenticate(KernelBrowser $client, int $id = 990001): void {
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");
        $connection->executeStatement("INSERT INTO identity_users (id, login, display_name, role, legacy_group_id, legacy_user_priv, enabled, password_hash, imported_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE login=VALUES(login), display_name=VALUES(display_name), enabled=VALUES(enabled), password_hash=VALUES(password_hash), updated_at=VALUES(updated_at)", [$id, "test-user-".$id, "Тестовый пользователь", "ROLE_MAIN", 1, 0, 1, password_hash("test-password", PASSWORD_ARGON2ID), $now, $now]);
        $connection->update('identity_users', ['max_active_sessions' => User::DEFAULT_MAX_ACTIVE_SESSIONS], ['id' => $id]);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 1030");
        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [$id, $permissionId]);
        $connection->executeStatement("DELETE FROM identity_user_sessions WHERE user_id = ?", [$id]);

        $client->request("GET", "/login");
        $client->submitForm("Вход", ["login" => "test-user-".$id, "password" => "test-password"]);
    }
}
