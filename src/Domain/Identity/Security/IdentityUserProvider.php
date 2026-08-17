<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class IdentityUserProvider implements UserProviderInterface {
    public function __construct(private Connection $connection) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface {
        $row = $this->connection->fetchAssociative("SELECT id, login, display_name, password_hash, enabled FROM identity_users WHERE login = ?", [$identifier]);

        if ($row === false) {
            throw new UserNotFoundException();
        }

        return $this->user($row);
    }

    public function refreshUser(UserInterface $user): UserInterface {
        if (!$user instanceof IdentityUser) {
            throw new \InvalidArgumentException("Unsupported user class.");
        }

        $row = $this->connection->fetchAssociative("SELECT id, login, display_name, password_hash, enabled FROM identity_users WHERE id = ?", [$user->getId()]);

        if ($row === false) {
            throw new UserNotFoundException();
        }

        return $this->user($row);
    }

    public function supportsClass(string $class): bool {
        return $class === IdentityUser::class;
    }

    /** @param array{id: int|string, login: string, display_name: string, password_hash: ?string, enabled: int|string} $row */
    private function user(array $row): IdentityUser {
        return new IdentityUser((int) $row["id"], $row["login"], $row["display_name"], $row["password_hash"], (bool) $row["enabled"]);
    }
}
