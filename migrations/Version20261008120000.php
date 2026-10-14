<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008120000 extends AbstractMigration {
    public function getDescription(): string {
        return 'Individual session limits without evicting existing logins; preserves current sessions.';
    }

    public function isTransactional(): bool {
        return false;
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE identity_users ADD max_active_sessions SMALLINT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE identity_user_sessions DROP PRIMARY KEY, ADD PRIMARY KEY (user_id, token_hash)');
    }

    public function down(Schema $schema): void {
        $this->abortIf(
            (bool) $this->connection->fetchOne('SELECT user_id FROM identity_user_sessions GROUP BY user_id HAVING COUNT(*) > 1 LIMIT 1'),
            'Cannot restore a single-session primary key while users have multiple sessions. No sessions were removed.',
        );
        $this->addSql('ALTER TABLE identity_user_sessions DROP PRIMARY KEY, ADD PRIMARY KEY (user_id)');
        $this->addSql('ALTER TABLE identity_users DROP max_active_sessions');
    }
}
