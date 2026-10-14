<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity\Security;

use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repository\IdentityMenuRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class DivisionAccessVoterTest extends KernelTestCase {
    public function testItGrantsOnlyAnExplicitLegacyDivisionPermission(): void {
        self::bootKernel();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");
        $connection->executeStatement("INSERT INTO identity_users (id, login, display_name, role, legacy_group_id, legacy_user_priv, enabled, imported_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), role = VALUES(role)", [990004, "voter-user", "Voter user", "ROLE_MAIN", 1, 0, 1, $now, $now]);
        $connection->executeStatement("INSERT INTO identity_user_permissions (user_id, permission_id) SELECT ?, id FROM identity_permissions WHERE legacy_division_id = 1030 ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id)", [990004]);
        $voter = new DivisionAccessVoter(new IdentityMenuRepository($connection));
        $user = new User(990004, "test", "Test", null, true);
        $user->setRole(\App\Domain\Identity\Enums\UserRole::Main);
        $token = new UsernamePasswordToken($user, "main");

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, 1030, [DivisionAccessVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, 987654321, [DivisionAccessVoter::ATTRIBUTE]));
    }
}
