<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100000 extends AbstractMigration {
    public function getDescription(): string {

        return 'Ссылка отменённой CRM-сделки на исходный заказ.';
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE crm_deals ADD source_order_id BIGINT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CRM_DEAL_SOURCE_ORDER ON crm_deals (source_order_id)');
    }

    public function down(Schema $schema): void {
        $this->addSql('DROP INDEX IDX_CRM_DEAL_SOURCE_ORDER ON crm_deals');
        $this->addSql('ALTER TABLE crm_deals DROP source_order_id');
    }
}
