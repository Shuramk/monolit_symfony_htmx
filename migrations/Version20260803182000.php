<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803182000 extends AbstractMigration {
    public function getDescription(): string {
        return "Creates imported enterprise warranty text storage.";
    }

    public function up(Schema $schema): void {
        $this->addSql("CREATE TABLE enterprise_warranty_texts (id BIGINT AUTO_INCREMENT NOT NULL, enterprise_profile_id BIGINT NOT NULL, source_html LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_ENTERPRISE_WARRANTY (enterprise_profile_id), PRIMARY KEY(id), CONSTRAINT FK_ENTERPRISE_WARRANTY FOREIGN KEY (enterprise_profile_id) REFERENCES enterprise_profiles (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void {
        $this->addSql("DROP TABLE enterprise_warranty_texts");
    }
}
