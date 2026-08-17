<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803181000 extends AbstractMigration {
    public function getDescription(): string {
        return "Uses legacy enterprise IDs as primary keys.";
    }

    public function up(Schema $schema): void {
        $this->addSql("UPDATE enterprise_profiles SET id = legacy_eid WHERE id != legacy_eid");
        $this->addSql("ALTER TABLE enterprise_profiles DROP INDEX UNIQ_ENTERPRISE_LEGACY, DROP COLUMN legacy_eid");
    }

    public function down(Schema $schema): void {
        $this->addSql("ALTER TABLE enterprise_profiles ADD legacy_eid INT NOT NULL AFTER id, ADD UNIQUE INDEX UNIQ_ENTERPRISE_LEGACY (legacy_eid)");
        $this->addSql("UPDATE enterprise_profiles SET legacy_eid = id");
    }
}
