<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Creates the application-owned Product catalog while preserving legacy product IDs.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE products (id BIGINT NOT NULL, parent_id BIGINT DEFAULT NULL, name VARCHAR(510) NOT NULL, name_uk VARCHAR(510) DEFAULT NULL, unit VARCHAR(255) NOT NULL, storage_office NUMERIC(12, 3) NOT NULL DEFAULT 0, storage_workshop NUMERIC(12, 3) NOT NULL DEFAULT 0, storage_third NUMERIC(12, 3) NOT NULL DEFAULT 0, price NUMERIC(12, 4) NOT NULL, sorting INT NOT NULL DEFAULT 0, type SMALLINT NOT NULL DEFAULT 0, pallets VARCHAR(255) DEFAULT NULL, inventory_name VARCHAR(255) DEFAULT NULL, forms VARCHAR(255) DEFAULT NULL, weight_per_unit NUMERIC(12, 3) DEFAULT NULL, min_quantity NUMERIC(12, 3) DEFAULT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, imported_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_PRODUCTS_PARENT (parent_id), INDEX IDX_PRODUCTS_ENABLED_SORTING (enabled, sorting, id), PRIMARY KEY(id), CONSTRAINT FK_PRODUCTS_PARENT FOREIGN KEY (parent_id) REFERENCES products (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE products");
    }
}
