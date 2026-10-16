<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016150000 extends AbstractMigration {
    public function getDescription(): string {
        return 'Заявки и приёмка возвратов без изменения кассы и исторических остатков.';
    }

    public function up(Schema $schema): void {
        $this->addSql("CREATE TABLE order_return_drafts (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, created_at DATETIME NOT NULL, comment TEXT NOT NULL, done BOOLEAN NOT NULL DEFAULT 0, operation_id CHAR(36) DEFAULT NULL UNIQUE, request_hash CHAR(64) DEFAULT NULL, source_payload LONGTEXT DEFAULT NULL, INDEX IDX_RETURN_DRAFT_STATE (done, created_at)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
        $this->addSql("CREATE TABLE order_return_draft_items (id BIGINT AUTO_INCREMENT PRIMARY KEY, draft_id BIGINT NOT NULL, product_id BIGINT NOT NULL, quantity DECIMAL(15,3) NOT NULL, INDEX IDX_RETURN_DRAFT_ITEM (draft_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
        $this->addSql("CREATE TABLE order_returns (id BIGINT AUTO_INCREMENT PRIMARY KEY, order_id BIGINT NOT NULL, draft_id BIGINT DEFAULT NULL UNIQUE, accepted_at DATETIME NOT NULL, accepted_by BIGINT NOT NULL, processed_at DATETIME DEFAULT NULL, processed_by BIGINT DEFAULT NULL, refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0, operation_id CHAR(36) DEFAULT NULL UNIQUE, request_hash CHAR(64) DEFAULT NULL, source_payload LONGTEXT DEFAULT NULL, INDEX IDX_RETURN_ORDER (order_id, id), INDEX IDX_RETURN_PENDING (processed_by, accepted_at)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
        $this->addSql("CREATE TABLE order_return_items (id BIGINT AUTO_INCREMENT PRIMARY KEY, return_id BIGINT NOT NULL, product_id BIGINT NOT NULL, quantity DECIMAL(15,3) NOT NULL, price DECIMAL(19,4) NOT NULL, INDEX IDX_RETURN_ITEM (return_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
        $this->addSql("CREATE TABLE order_return_imports (id VARCHAR(40) PRIMARY KEY, completed_at DATETIME DEFAULT NULL, report LONGTEXT DEFAULT NULL) ENGINE=InnoDB");
        $this->addSql("INSERT INTO order_return_imports (id) VALUES ('legacy-v1')");
        $this->addSql("INSERT INTO identity_permissions (code,title,legacy_division_id) SELECT 'legacy.did.115','Создать заявку на возврат',115 WHERE NOT EXISTS (SELECT 1 FROM identity_permissions WHERE legacy_division_id=115)");
        $this->addSql("INSERT IGNORE INTO identity_user_permissions (user_id,permission_id) SELECT a.user_id,p115.id FROM identity_user_permissions a INNER JOIN identity_permissions p114 ON p114.id=a.permission_id AND p114.legacy_division_id=114 CROSS JOIN identity_permissions p115 WHERE p115.legacy_division_id=115");
    }

    public function down(Schema $schema): void {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM order_returns') > 0 || (int) $this->connection->fetchOne('SELECT COUNT(*) FROM order_return_drafts') > 0 || (int) $this->connection->fetchOne('SELECT COUNT(*) FROM order_return_items') > 0, 'Нельзя удалять данные возвратов автоматическим откатом.');
        foreach (['order_return_items', 'order_returns', 'order_return_draft_items', 'order_return_drafts', 'order_return_imports'] as $table) {
            $this->addSql('DROP TABLE ' . $table);
        }
    }
}
