<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007120000 extends AbstractMigration {
    public function getDescription(): string {
        return 'Adds an application order changelog with immutable JSON snapshots.';
    }

    public function up(Schema $schema): void {
        $this->addSql("CREATE TABLE order_changelog (id BIGINT AUTO_INCREMENT NOT NULL, order_id BIGINT NOT NULL, event VARCHAR(64) NOT NULL, message TEXT NOT NULL, snapshot JSON NOT NULL, user_id BIGINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), INDEX idx_order_changelog_order_date (order_id, created_at), CONSTRAINT fk_order_changelog_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void {
        $this->addSql('DROP TABLE order_changelog');
    }
}
