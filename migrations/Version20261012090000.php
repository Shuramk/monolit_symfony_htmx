<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012090000 extends AbstractMigration {
    public function getDescription(): string { return 'CRM: сделки, контакты, задачи, участники, история, уведомления, файлы и счётчики.'; }
    public function up(Schema $schema): void {
        $suffix = ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        $this->addSql('CREATE TABLE crm_deals (id BIGINT AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT DEFAULT NULL, author_id BIGINT DEFAULT NULL, stacker_id BIGINT DEFAULT NULL, source_id INT DEFAULT NULL, comment LONGTEXT NOT NULL, director_comment LONGTEXT NOT NULL, budget DECIMAL(12,2) NOT NULL DEFAULT 0, markup SMALLINT NOT NULL DEFAULT 0, shipping_address TEXT DEFAULT NULL, desired_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, state VARCHAR(16) NOT NULL DEFAULT \'active\', converted_order_id BIGINT DEFAULT NULL, version INT NOT NULL DEFAULT 0, INDEX IDX_CRM_DEAL_STATE(state,created_at), INDEX IDX_CRM_DEAL_AUTHOR(author_id), UNIQUE INDEX UNIQ_CRM_DEAL_ORDER(converted_order_id))'.$suffix);
        $this->addSql('CREATE TABLE crm_deal_items (id BIGINT AUTO_INCREMENT PRIMARY KEY, deal_id BIGINT NOT NULL, product_id BIGINT NOT NULL, quantity DECIMAL(12,3) NOT NULL, price DECIMAL(12,4) NOT NULL, UNIQUE INDEX UNIQ_CRM_DEAL_PRODUCT(deal_id,product_id), CONSTRAINT FK_CRM_ITEM_DEAL FOREIGN KEY(deal_id) REFERENCES crm_deals(id) ON DELETE CASCADE)'.$suffix);
        $this->addSql('CREATE TABLE crm_deal_contacts (id BIGINT AUTO_INCREMENT PRIMARY KEY, deal_id BIGINT NOT NULL, author_id BIGINT DEFAULT NULL, executor_id BIGINT DEFAULT NULL, due_at DATETIME NOT NULL, comment LONGTEXT NOT NULL, completed_at DATETIME DEFAULT NULL, INDEX IDX_CRM_CONTACT_DEAL(deal_id,completed_at,id), INDEX IDX_CRM_CONTACT_EXECUTOR(executor_id,due_at), CONSTRAINT FK_CRM_CONTACT_DEAL FOREIGN KEY(deal_id) REFERENCES crm_deals(id) ON DELETE CASCADE)'.$suffix);
        $this->addSql('CREATE TABLE crm_deal_history (id BIGINT AUTO_INCREMENT PRIMARY KEY, deal_id BIGINT DEFAULT NULL, legacy_deal_id BIGINT DEFAULT NULL, author_id BIGINT DEFAULT NULL, happened_at DATETIME NOT NULL, details LONGTEXT NOT NULL, cancel_comment TEXT DEFAULT NULL, cancel_type INT DEFAULT NULL, INDEX IDX_CRM_DEAL_HISTORY(deal_id,happened_at), CONSTRAINT FK_CRM_HISTORY_DEAL FOREIGN KEY(deal_id) REFERENCES crm_deals(id) ON DELETE CASCADE)'.$suffix);
        $this->addSql('CREATE TABLE crm_tasks (id BIGINT AUTO_INCREMENT PRIMARY KEY, author_id BIGINT DEFAULT NULL, executor_id BIGINT DEFAULT NULL, created_at DATETIME NOT NULL, due_at DATETIME DEFAULT NULL, text LONGTEXT NOT NULL, director_comment LONGTEXT NOT NULL, last_comment LONGTEXT NOT NULL, completed_at DATETIME DEFAULT NULL, cancelled TINYINT NOT NULL DEFAULT 0, hidden TINYINT NOT NULL DEFAULT 0, version INT NOT NULL DEFAULT 0, INDEX IDX_CRM_TASK_EXECUTOR(executor_id,completed_at,cancelled,hidden,due_at), INDEX IDX_CRM_TASK_AUTHOR(author_id,completed_at))'.$suffix);
        $this->addSql('CREATE TABLE crm_task_participants (task_id BIGINT NOT NULL, user_id BIGINT NOT NULL, kind VARCHAR(16) NOT NULL, PRIMARY KEY(task_id,user_id,kind), INDEX IDX_CRM_PARTICIPANT_USER(user_id,kind), CONSTRAINT FK_CRM_PARTICIPANT_TASK FOREIGN KEY(task_id) REFERENCES crm_tasks(id) ON DELETE CASCADE)'.$suffix);
        $this->addSql('CREATE TABLE crm_task_history (id BIGINT AUTO_INCREMENT PRIMARY KEY, task_id BIGINT NOT NULL, author_id BIGINT DEFAULT NULL, happened_at DATETIME NOT NULL, details LONGTEXT NOT NULL, INDEX IDX_CRM_TASK_HISTORY(task_id,happened_at), CONSTRAINT FK_CRM_HISTORY_TASK FOREIGN KEY(task_id) REFERENCES crm_tasks(id) ON DELETE CASCADE)'.$suffix);
        $this->addSql('CREATE TABLE crm_task_notifications (task_id BIGINT NOT NULL, user_id BIGINT NOT NULL, PRIMARY KEY(task_id,user_id), INDEX IDX_CRM_NOTIFICATION_USER(user_id), CONSTRAINT FK_CRM_NOTIFICATION_TASK FOREIGN KEY(task_id) REFERENCES crm_tasks(id) ON DELETE CASCADE)'.$suffix);
        $this->addSql('CREATE TABLE crm_task_files (id BIGINT AUTO_INCREMENT PRIMARY KEY, task_id BIGINT NOT NULL, original_name VARCHAR(255) NOT NULL, storage_name VARCHAR(255) NOT NULL, mime VARCHAR(120) NOT NULL DEFAULT \'application/octet-stream\', size BIGINT NOT NULL DEFAULT 0, available TINYINT NOT NULL DEFAULT 1, removed TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, INDEX IDX_CRM_FILE_TASK(task_id,removed), CONSTRAINT FK_CRM_FILE_TASK FOREIGN KEY(task_id) REFERENCES crm_tasks(id) ON DELETE CASCADE)'.$suffix);
        $this->addSql('CREATE TABLE crm_counters (day DATE PRIMARY KEY, calls INT NOT NULL DEFAULT 0, new_calls INT NOT NULL DEFAULT 0, visitors INT NOT NULL DEFAULT 0, new_visitors INT NOT NULL DEFAULT 0)'.$suffix);
        $this->addSql('CREATE TABLE crm_imports (name VARCHAR(32) PRIMARY KEY, imported_at DATETIME NOT NULL)'.$suffix);
    }
    public function down(Schema $schema): void {
        foreach (['crm_imports','crm_counters','crm_task_files','crm_task_notifications','crm_task_history','crm_task_participants','crm_tasks','crm_deal_history','crm_deal_contacts','crm_deal_items','crm_deals'] as $table) $this->addSql('DROP TABLE '.$table);
    }
}
