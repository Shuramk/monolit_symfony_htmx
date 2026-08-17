<?php

declare(strict_types=1);

namespace App\Domain\Identity\Audit;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

final readonly class AuthenticationAuditRepository {
    public function __construct(private Connection $connection) {
    }

    public function record(string $eventType, ?int $userId, ?string $login, Request $request): void {
        $this->connection->insert("identity_authentication_audit", [
            "event_type" => $eventType,
            "legacy_user_id" => $userId,
            "login" => $login !== null ? mb_substr($login, 0, 191) : null,
            "ip_address" => $request->getClientIp(),
            "user_agent" => mb_substr((string) $request->headers->get("User-Agent"), 0, 512),
            "occurred_at" => (new \DateTimeImmutable())->format("Y-m-d H:i:s"),
        ]);
    }
}
