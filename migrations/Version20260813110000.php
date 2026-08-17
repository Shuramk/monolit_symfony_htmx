<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813110000 extends AbstractMigration {
    public function getDescription(): string {
        return "Imports disabled users and stores one active Symfony session per user.";
    }

    public function up(Schema $schema): void {
        $this->addSql("ALTER TABLE identity_users ADD enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER legacy_user_priv");
        $this->addSql("CREATE TABLE identity_user_sessions (user_id BIGINT NOT NULL, token_hash CHAR(64) NOT NULL, last_seen_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(user_id), CONSTRAINT FK_IDENTITY_SESSION_USER FOREIGN KEY (user_id) REFERENCES identity_users (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void {
        $this->addSql("DROP TABLE identity_user_sessions");
        $this->addSql("ALTER TABLE identity_users DROP enabled");
    }
}
