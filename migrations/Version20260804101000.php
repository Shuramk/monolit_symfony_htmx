<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804101000 extends AbstractMigration {
    public function getDescription(): string {
        return "Removes unused group permission grants; legacy groups do not grant access.";
    }

    public function up(Schema $schema): void {
        $this->addSql("DROP TABLE identity_group_permissions");
    }

    public function down(Schema $schema): void {
        $this->addSql("CREATE TABLE identity_group_permissions (legacy_group_id INT NOT NULL, permission_id BIGINT NOT NULL, PRIMARY KEY(legacy_group_id, permission_id), CONSTRAINT FK_IDENTITY_GROUP_PERMISSION_GROUP FOREIGN KEY (legacy_group_id) REFERENCES identity_groups (legacy_group_id) ON DELETE CASCADE, CONSTRAINT FK_IDENTITY_GROUP_PERMISSION_PERMISSION FOREIGN KEY (permission_id) REFERENCES identity_permissions (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }
}
