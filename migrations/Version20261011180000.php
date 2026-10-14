<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011180000 extends AbstractMigration {
    public function getDescription(): string { return 'Строковые роли пользователей; старые номера остаются только метаданными импорта.'; }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE identity_users ADD role VARCHAR(32) DEFAULT NULL, MODIFY legacy_group_id INT NOT NULL DEFAULT 0');
        $this->addSql("UPDATE identity_users SET role = CASE legacy_group_id WHEN 1 THEN 'ROLE_MAIN' WHEN 2 THEN 'ROLE_SALES' WHEN 3 THEN 'ROLE_PRODUCTION' WHEN 10 THEN 'ROLE_SHIFT_SUPERVISOR' WHEN 99 THEN 'ROLE_PARTNER' ELSE NULL END");
    }

    public function down(Schema $schema): void {
        $this->addSql("UPDATE identity_users SET legacy_group_id = CASE role WHEN 'ROLE_MAIN' THEN 1 WHEN 'ROLE_SALES' THEN 2 WHEN 'ROLE_PRODUCTION' THEN 3 WHEN 'ROLE_SHIFT_SUPERVISOR' THEN 10 WHEN 'ROLE_PARTNER' THEN 99 ELSE 1000 END");
        $this->addSql('ALTER TABLE identity_users DROP role, MODIFY legacy_group_id INT NOT NULL');
    }
}
