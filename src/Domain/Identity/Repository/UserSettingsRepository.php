<?php declare(strict_types=1);

namespace App\Domain\Identity\Repository;

use Doctrine\DBAL\Connection;

final readonly class UserSettingsRepository {
    public function __construct(private Connection $connection) {}
    public function users(bool $hideInactive = true): array {
        return $this->connection->fetchAllAssociative('SELECT id,login,display_name,role,enabled FROM identity_users'.($hideInactive ? ' WHERE enabled=1' : '').' ORDER BY display_name,id');
    }
    public function permissions(): array {
        return $this->connection->fetchAllAssociative('SELECT p.id,p.legacy_division_id,p.title,COALESCE(d.parent_id,0) AS parent_id,COALESCE(d.sub_parent_id,0) AS sub_parent_id,COALESCE(d.enabled,0) AS enabled FROM identity_permissions p LEFT JOIN identity_legacy_divisions d ON d.id=p.legacy_division_id WHERE p.legacy_division_id IS NOT NULL ORDER BY COALESCE(d.priority,1000),p.legacy_division_id');
    }
    public function nextId(): int {
        $this->connection->executeStatement('INSERT INTO identity_user_numbers () VALUES ()');
        return (int)$this->connection->lastInsertId();
    }
    public function assigned(int $id): array {
        return array_map('intval',$this->connection->fetchFirstColumn('SELECT p.legacy_division_id FROM identity_user_permissions up JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=?',[$id]));
    }
    public function replacePermissions(int $id, array $divisionIDs): void {
        $catalog = array_column($this->permissions(),'id','legacy_division_id');
        $this->connection->delete('identity_user_permissions',['user_id'=>$id]);
        foreach ($divisionIDs as $divisionID) {
            if (isset($catalog[$divisionID])) $this->connection->insert('identity_user_permissions',['user_id'=>$id,'permission_id'=>$catalog[$divisionID]]);
        }
    }
    public function transactional(callable $operation): mixed { return $this->connection->transactional($operation); }
    public function revokeSessions(int $id): void { $this->connection->delete('identity_user_sessions',['user_id'=>$id]); }
}
