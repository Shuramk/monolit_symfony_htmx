<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repository;

use Doctrine\DBAL\Connection;

final readonly class IdentitySessionRepository {
    public function __construct(private Connection $connection) {
    }

    public function replace(int $userId, string $sessionId, \DateTimeImmutable $now): void {
        $expiresAt = $now->modify("+60 minutes");
        $this->connection->executeStatement("INSERT INTO identity_user_sessions (user_id, token_hash, last_seen_at, expires_at, created_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash), last_seen_at=VALUES(last_seen_at), expires_at=VALUES(expires_at), created_at=VALUES(created_at)", [$userId, hash("sha256", $sessionId), $now->format("Y-m-d H:i:s"), $expiresAt->format("Y-m-d H:i:s"), $now->format("Y-m-d H:i:s")]);
    }

    public function isCurrent(int $userId, string $sessionId, \DateTimeImmutable $now): bool {
        $session = $this->connection->fetchAssociative("SELECT token_hash, expires_at FROM identity_user_sessions WHERE user_id = ?", [$userId]);

        if ($session === false || !hash_equals($session["token_hash"], hash("sha256", $sessionId)) || new \DateTimeImmutable($session["expires_at"]) <= $now) {
            return false;
        }

        $this->connection->executeStatement("UPDATE identity_user_sessions SET last_seen_at = ?, expires_at = ? WHERE user_id = ?", [$now->format("Y-m-d H:i:s"), $now->modify("+60 minutes")->format("Y-m-d H:i:s"), $userId]);

        return true;
    }

    public function remove(int $userId): void {
        $this->connection->executeStatement("DELETE FROM identity_user_sessions WHERE user_id = ?", [$userId]);
    }
}
