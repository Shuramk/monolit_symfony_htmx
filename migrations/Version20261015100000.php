<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015100000 extends AbstractMigration {
    public function getDescription(): string {

        return 'Версии задач на отгрузку, перевозчики и номера фактических отгрузок.';
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE warehouse_shipping_tasks ADD version INT NOT NULL DEFAULT 0, ADD operation_key VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SHIPPING_OPERATION ON warehouse_shipping_tasks (operation_key)');
        $this->addSql('CREATE INDEX IDX_ORDER_SHIPMENT_TASK ON order_shipments (task_id)');
        $this->addSql('CREATE TABLE shipping_shipment_numbers (id BIGINT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
        $this->addSql('INSERT INTO shipping_shipment_numbers (id) SELECT GREATEST(COALESCE(MAX(id), 0), 1) FROM order_shipments');
        $this->addSql('CREATE TABLE shipping_carriers (id BIGINT PRIMARY KEY, name VARCHAR(510) NOT NULL, phone VARCHAR(255) DEFAULT NULL, priority INT NOT NULL DEFAULT 0, payload LONGTEXT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }

    public function down(Schema $schema): void {
        $this->addSql('DROP TABLE shipping_carriers');
        $this->addSql('DROP TABLE shipping_shipment_numbers');
        $this->addSql('DROP INDEX IDX_ORDER_SHIPMENT_TASK ON order_shipments');
        $this->addSql('ALTER TABLE warehouse_shipping_tasks DROP INDEX UNIQ_SHIPPING_OPERATION, DROP version, DROP operation_key');
    }
}
