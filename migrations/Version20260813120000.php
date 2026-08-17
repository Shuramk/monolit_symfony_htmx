<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813120000 extends AbstractMigration {
    public function getDescription(): string {
        return "Stores Symfony authentication audit events independently from imported users.";
    }

    public function up(Schema $schema): void {
        $this->addSql("CREATE TABLE identity_authentication_audit (id BIGINT AUTO_INCREMENT NOT NULL, event_type VARCHAR(32) NOT NULL, legacy_user_id BIGINT DEFAULT NULL, login VARCHAR(191) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, occurred_at DATETIME NOT NULL, INDEX IDX_IDENTITY_AUTH_AUDIT_USER (legacy_user_id), INDEX IDX_IDENTITY_AUTH_AUDIT_OCCURRED (occurred_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void {
        $this->addSql("DROP TABLE identity_authentication_audit");
    }
}
