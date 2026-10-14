<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009120000 extends AbstractMigration {
    public function getDescription(): string { return 'Признаки хранения денег по оплатам и идемпотентное добавление оплаты без движения кассы.'; }
    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE order_payments ADD money_taken SMALLINT DEFAULT NULL, ADD to_card TINYINT(1) DEFAULT NULL, ADD payment_markup SMALLINT DEFAULT NULL, ADD metadata_imported_at DATETIME DEFAULT NULL, ADD operation_id VARCHAR(36) DEFAULT NULL, ADD UNIQUE INDEX UNIQ_ORDER_PAYMENT_OPERATION (operation_id)');
        $this->addSql('CREATE TABLE order_payment_numbers (id BIGINT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }
    public function down(Schema $schema): void {
        $this->addSql('DROP TABLE order_payment_numbers');
        $this->addSql('ALTER TABLE order_payments DROP INDEX UNIQ_ORDER_PAYMENT_OPERATION, DROP money_taken, DROP to_card, DROP payment_markup, DROP metadata_imported_at, DROP operation_id');
    }
}
