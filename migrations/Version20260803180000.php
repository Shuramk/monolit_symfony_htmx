<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the add-only enterprise profile schema.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE enterprise_profiles (id BIGINT AUTO_INCREMENT NOT NULL, legacy_eid INT NOT NULL, legal_name_vat VARCHAR(255) DEFAULT NULL, legal_name_pe VARCHAR(255) DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, short_name VARCHAR(255) DEFAULT NULL, edrpou_vat VARCHAR(255) DEFAULT NULL, edrpou_pe VARCHAR(255) DEFAULT NULL, payment_account_vat VARCHAR(255) DEFAULT NULL, payment_account_pe VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, logo_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_ENTERPRISE_LEGACY (legacy_eid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE enterprise_phones (id BIGINT AUTO_INCREMENT NOT NULL, enterprise_profile_id BIGINT NOT NULL, kind VARCHAR(20) NOT NULL, position SMALLINT NOT NULL, number VARCHAR(32) NOT NULL, INDEX IDX_ENTERPRISE_PHONE_PROFILE (enterprise_profile_id), UNIQUE INDEX UNIQ_ENTERPRISE_PHONE_POSITION (enterprise_profile_id, kind, position), PRIMARY KEY(id), CONSTRAINT FK_ENTERPRISE_PHONE_PROFILE FOREIGN KEY (enterprise_profile_id) REFERENCES enterprise_profiles (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE enterprise_phones');
        $this->addSql('DROP TABLE enterprise_profiles');
    }
}
