<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity\Security;

use App\Domain\Identity\Repository\IdentitySessionRepository;
use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IdentitySessionRepositoryTest extends KernelTestCase {
    use IdentityTestUser;

    public function testReplacingSessionInvalidatesThePreviousSession(): void {
        self::bootKernel();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        $now = new \DateTimeImmutable();
        $connection->executeStatement("INSERT INTO identity_users (id, login, display_name, legacy_group_id, legacy_user_priv, enabled, imported_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)", [990003, "session-user", "Session user", 1, 0, 1, $now->format("Y-m-d H:i:s"), $now->format("Y-m-d H:i:s")]);
        $repository = self::getContainer()->get(IdentitySessionRepository::class);

        $repository->replace(990003, "first-session", $now);
        $repository->replace(990003, "second-session", $now);

        self::assertFalse($repository->isCurrent(990003, "first-session", $now));
        self::assertTrue($repository->isCurrent(990003, "second-session", $now));
    }
}
