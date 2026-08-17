<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804100000 extends AbstractMigration {
    public function getDescription(): string {
        return "Creates application-owned identity, permission, and legacy navigation import tables.";
    }

    public function up(Schema $schema): void {
        $this->addSql("CREATE TABLE identity_users (id BIGINT NOT NULL, login VARCHAR(30) NOT NULL, display_name VARCHAR(255) NOT NULL, legacy_group_id INT NOT NULL, legacy_user_priv INT NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, password_hash_updated_at DATETIME DEFAULT NULL, imported_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTITY_USER_LOGIN (login), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE identity_groups (legacy_group_id INT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(legacy_group_id), UNIQUE INDEX UNIQ_IDENTITY_GROUP_CODE (code)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE identity_permissions (id BIGINT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, legacy_division_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTITY_PERMISSION_CODE (code), UNIQUE INDEX UNIQ_IDENTITY_PERMISSION_DID (legacy_division_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE identity_user_permissions (user_id BIGINT NOT NULL, permission_id BIGINT NOT NULL, PRIMARY KEY(user_id, permission_id), CONSTRAINT FK_IDENTITY_USER_PERMISSION_USER FOREIGN KEY (user_id) REFERENCES identity_users (id) ON DELETE CASCADE, CONSTRAINT FK_IDENTITY_USER_PERMISSION_PERMISSION FOREIGN KEY (permission_id) REFERENCES identity_permissions (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE identity_group_permissions (legacy_group_id INT NOT NULL, permission_id BIGINT NOT NULL, PRIMARY KEY(legacy_group_id, permission_id), CONSTRAINT FK_IDENTITY_GROUP_PERMISSION_GROUP FOREIGN KEY (legacy_group_id) REFERENCES identity_groups (legacy_group_id) ON DELETE CASCADE, CONSTRAINT FK_IDENTITY_GROUP_PERMISSION_PERMISSION FOREIGN KEY (permission_id) REFERENCES identity_permissions (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE identity_legacy_divisions (id INT NOT NULL, parent_id INT NOT NULL, sub_parent_id INT NOT NULL, title VARCHAR(255) NOT NULL, module_key VARCHAR(255) NOT NULL, enabled TINYINT(1) NOT NULL, priority SMALLINT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void {
        $this->addSql("DROP TABLE identity_user_permissions");
        $this->addSql("DROP TABLE identity_group_permissions");
        $this->addSql("DROP TABLE identity_legacy_divisions");
        $this->addSql("DROP TABLE identity_permissions");
        $this->addSql("DROP TABLE identity_groups");
        $this->addSql("DROP TABLE identity_users");
    }
}
