<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repository;

use App\Domain\Identity\Entities\User;
use Doctrine\DBAL\Connection;

final readonly class IdentitySessionRepository {
    public function __construct(private Connection $connection) {
    }

    public function register(int $userId, string $sessionId, \DateTimeImmutable $now): void {
        $this->connection->transactional(function (Connection $connection) use ($userId, $sessionId, $now): void {
            // Lock the user, not just existing sessions: even an empty session list must be serialized.
            $limit = $connection->fetchOne('SELECT max_active_sessions FROM identity_users WHERE id = ? FOR UPDATE', [$userId]);
            if ($limit === false) {
                throw new \LogicException('Пользователь не найден.');
            }

            $timestamp = $now->format('Y-m-d H:i:s');
            $tokenHash = hash('sha256', $sessionId);
            $connection->executeStatement('DELETE FROM identity_user_sessions WHERE user_id = ? AND expires_at <= ?', [$userId, $timestamp]);
            // A locking read sees the latest committed rows, including under REPEATABLE READ.
            $activeHashes = $connection->fetchFirstColumn('SELECT token_hash FROM identity_user_sessions WHERE user_id = ? ORDER BY created_at ASC, token_hash ASC FOR UPDATE', [$userId]);
            if (in_array($tokenHash, $activeHashes, true)) {
                $this->isCurrent($userId, $sessionId, $now);

                return;
            }

            $sessionsToEvict = max(0, count($activeHashes) - (int) $limit + 1);
            foreach (array_slice($activeHashes, 0, $sessionsToEvict) as $oldestHash) {
                $connection->delete('identity_user_sessions', ['user_id' => $userId, 'token_hash' => $oldestHash]);
            }

            $connection->insert('identity_user_sessions', [
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'last_seen_at' => $timestamp,
                'expires_at' => $now->modify('+'.User::SESSION_IDLE_TIMEOUT_SECONDS.' seconds')->format('Y-m-d H:i:s'),
                'created_at' => $timestamp,
            ]);
        });
    }

    public function isCurrent(int $userId, string $sessionId, \DateTimeImmutable $now): bool {
        $tokenHash = hash('sha256', $sessionId);
        $session = $this->connection->fetchAssociative("SELECT expires_at FROM identity_user_sessions WHERE user_id = ? AND token_hash = ?", [$userId, $tokenHash]);

        if ($session === false || new \DateTimeImmutable($session["expires_at"]) <= $now) {
            return false;
        }

        $this->connection->executeStatement("UPDATE identity_user_sessions SET last_seen_at = ?, expires_at = ? WHERE user_id = ? AND token_hash = ?", [$now->format("Y-m-d H:i:s"), $now->modify('+'.User::SESSION_IDLE_TIMEOUT_SECONDS.' seconds')->format("Y-m-d H:i:s"), $userId, $tokenHash]);

        return true;
    }

    public function remove(int $userId, string $sessionId): void {
        $this->connection->delete('identity_user_sessions', ['user_id' => $userId, 'token_hash' => hash('sha256', $sessionId)]);
    }
}
