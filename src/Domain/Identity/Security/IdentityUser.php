<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class IdentityUser implements UserInterface, PasswordAuthenticatedUserInterface {
    public function __construct(
        private int $id,
        private string $login,
        private string $displayName,
        private ?string $passwordHash,
        private bool $enabled,
    ) {
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUserIdentifier(): string {
        return $this->login;
    }

    public function getDisplayName(): string {
        return $this->displayName;
    }

    public function getPassword(): ?string {
        return $this->passwordHash;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    /** @return list<string> */
    public function getRoles(): array {
        return ["ROLE_USER"];
    }

    public function eraseCredentials(): void {
    }
}
