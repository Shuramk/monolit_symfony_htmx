<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\Enums\UserRole;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'identity_users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTITY_USER_LOGIN', columns: ['login'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface {
    public const int DEFAULT_MAX_ACTIVE_SESSIONS = 1;
    public const int SESSION_IDLE_TIMEOUT_SECONDS = 60 * 60;

    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    private int $id;

    #[ORM\Column(length: 30)]
    private string $login;

    #[ORM\Column(name: 'display_name', length: 255)]
    private string $displayName;

    #[ORM\Column(length: 32, nullable: true, enumType: UserRole::class)]
    private ?UserRole $role = null;

    #[ORM\Column(name: 'start_page_id', nullable: true)]
    private ?int $startPageId = null;

    #[ORM\Column(name: 'legacy_user_priv')]
    private int $legacyUserPriv = 0;

    #[ORM\Column(options: ['default' => 0])]
    private bool $enabled;

    #[ORM\Column(name: 'max_active_sessions', type: 'smallint', options: ['default' => self::DEFAULT_MAX_ACTIVE_SESSIONS])]
    private int $maxActiveSessions = self::DEFAULT_MAX_ACTIVE_SESSIONS;

    #[ORM\Column(name: 'password_hash', length: 255, nullable: true)]
    private ?string $passwordHash;

    #[ORM\Column(name: 'password_hash_updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordHashUpdatedAt = null;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        string $login,
        string $displayName,
        ?string $passwordHash,
        bool $enabled,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->id = $id;
        $this->login = $login;
        $this->displayName = $displayName;
        $this->passwordHash = $passwordHash;
        $this->enabled = $enabled;
        $this->importedAt = $this->updatedAt = $now ?? new \DateTimeImmutable();
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

    public function canPrintWarehouseInventory(): bool {
        return $this->canAccess() && (in_array($this->getRole(), [UserRole::Main, UserRole::Production], true) || $this->id === 31);
    }

    public function canManageWarehouse(): bool { return $this->canAccess() && $this->getRole() === UserRole::Main; }
    public function canExecuteShipping(): bool {

        return $this->canAccess()
            && (in_array($this->getRole(), [UserRole::Main, UserRole::Production], true) || $this->id === 31);
    }
    public function canApproveShipping(): bool {

        return $this->canAccess() && in_array($this->getRole(), [UserRole::Main, UserRole::Sales], true);
    }
    public function canSeeWarehouseBreakdown(): bool { return $this->canAccess() && in_array($this->getRole(), [UserRole::Main, UserRole::Sales], true); }

    public function getRole(): ?UserRole { return $this->role; }
    public function canAccess(): bool { return $this->enabled && $this->role !== null; }
    public function setRole(?UserRole $role): void {
        $this->role = $role;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPassword(): ?string {
        return $this->passwordHash;
    }

    public function changeDetails(string $login, string $displayName, bool $enabled): void {
        $login = trim($login);
        $displayName = trim($displayName);
        if ($login === '' || mb_strlen($login) > 30 || preg_match('/\s/u', $login)) throw new \DomainException('Введите логин без пробелов, не длиннее 30 символов.');
        if ($displayName === '' || mb_strlen($displayName) > 255) throw new \DomainException('Введите имя пользователя.');
        $this->login = $login;
        $this->displayName = $displayName;
        $this->enabled = $enabled;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function changePassword(string $hash): void {
        $this->passwordHash = $hash;
        $this->passwordHashUpdatedAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getStartPageId(): ?int { return $this->startPageId; }
    public function setStartPageId(?int $id): void {
        $this->startPageId = $id;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getMaxActiveSessions(): int {
        return $this->maxActiveSessions;
    }

    public function setMaxActiveSessions(int $limit): void {
        if ($limit < 1 || $limit > 32767) {
            throw new \InvalidArgumentException('Лимит активных сессий должен быть от 1 до 32767.');
        }

        $this->maxActiveSessions = $limit;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return list<string> */
    public function getRoles(): array {
        return $this->canAccess() ? ['ROLE_USER', $this->role->value] : [];
    }

    public function eraseCredentials(): void {
    }
}
