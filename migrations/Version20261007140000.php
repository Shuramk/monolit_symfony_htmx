<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007140000 extends AbstractMigration {
    public function getDescription(): string {
        return 'Preserves the order manager for list filtering.';
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE orders ADD manager_id BIGINT DEFAULT NULL, ADD INDEX IDX_ORDERS_MANAGER (manager_id)');
    }

    public function down(Schema $schema): void {
        $this->addSql('ALTER TABLE orders DROP INDEX IDX_ORDERS_MANAGER, DROP manager_id');
    }
}
