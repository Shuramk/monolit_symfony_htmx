<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011190000 extends AbstractMigration {
    public function getDescription(): string { return 'Стартовая страница пользователя и безопасное выделение новых ID без изменения импортированных ID.'; }
    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE identity_users ADD start_page_id INT DEFAULT NULL');
        $this->addSql('CREATE TABLE identity_user_numbers (id BIGINT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addSql('INSERT INTO identity_user_numbers (id) SELECT GREATEST(COALESCE(MAX(id),0),1) FROM identity_users');
    }
    public function down(Schema $schema): void {
        $this->addSql('ALTER TABLE identity_users DROP start_page_id');
        $this->addSql('DROP TABLE identity_user_numbers');
    }
}
