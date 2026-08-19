<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818112000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Creates the application-owned Customer catalog and links imported orders to it.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE customers (id BIGINT NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(32) DEFAULT NULL, phone_secondary VARCHAR(32) DEFAULT NULL, phone_tertiary VARCHAR(32) DEFAULT NULL, registered_at DATETIME NOT NULL, person_type VARCHAR(10) DEFAULT NULL, contact_person VARCHAR(100) DEFAULT NULL, buyer_type VARCHAR(10) DEFAULT NULL, stacker_id BIGINT DEFAULT NULL, comment TEXT DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, imported_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_CUSTOMERS_NAME (name), INDEX IDX_CUSTOMERS_PHONE (phone), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("ALTER TABLE orders ADD customer_id BIGINT DEFAULT NULL, ADD INDEX IDX_ORDERS_CUSTOMER_ID (customer_id), ADD CONSTRAINT FK_ORDERS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE orders DROP FOREIGN KEY FK_ORDERS_CUSTOMER");
        $this->addSql("ALTER TABLE orders DROP INDEX IDX_ORDERS_CUSTOMER_ID, DROP COLUMN customer_id");
        $this->addSql("DROP TABLE customers");
    }
}
