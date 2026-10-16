<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015170000 extends AbstractMigration {
    public function getDescription(): string {

        return 'Доставка заказа, безопасная нумерация и идемпотентное создание клиентов.';
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE orders ADD carrier_id BIGINT DEFAULT NULL, ADD shipping_trips INT DEFAULT NULL, ADD shipping_unit_price DECIMAL(12,2) DEFAULT NULL');
        $this->addSql('CREATE TABLE customer_number_sequence (id INT PRIMARY KEY, last_id BIGINT NOT NULL) ENGINE=InnoDB');
        $this->addSql('INSERT INTO customer_number_sequence (id, last_id) SELECT 1, COALESCE(MAX(id), 0) FROM customers');
        $this->addSql('CREATE TABLE customer_creation_keys (operation_key VARCHAR(100) PRIMARY KEY, customer_id BIGINT NOT NULL, fingerprint CHAR(64) NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }

    public function down(Schema $schema): void {
        $this->addSql('DROP TABLE customer_creation_keys');
        $this->addSql('DROP TABLE customer_number_sequence');
        $this->addSql('ALTER TABLE orders DROP carrier_id, DROP shipping_trips, DROP shipping_unit_price');
    }
}
