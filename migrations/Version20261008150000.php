<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008150000 extends AbstractMigration {
    public function getDescription(): string {
        return 'Product settings: edit version, ID allocation and immutable change journal; preserves products and balances.';
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE products ADD edit_version INT NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE product_number_sequence (id INT NOT NULL, last_id BIGINT NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB');
        $this->addSql('INSERT INTO product_number_sequence SELECT 1, COALESCE(MAX(id), 0) FROM products');
        $this->addSql('CREATE TABLE product_changelog (id BIGINT AUTO_INCREMENT NOT NULL, product_id BIGINT NOT NULL, user_id BIGINT NOT NULL, event VARCHAR(20) NOT NULL, before_snapshot LONGTEXT DEFAULT NULL, after_snapshot LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_PRODUCT_CHANGELOG_PRODUCT (product_id, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }

    public function down(Schema $schema): void {
        $this->addSql('DROP TABLE product_changelog');
        $this->addSql('DROP TABLE product_number_sequence');
        $this->addSql('ALTER TABLE products DROP edit_version');
    }
}
