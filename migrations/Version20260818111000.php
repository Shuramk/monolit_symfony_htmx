<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818111000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Links imported order items to the application Product catalog when the legacy product still exists.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE order_items item LEFT JOIN products product ON product.id = item.product_id SET item.product_id = NULL WHERE item.product_id IS NOT NULL AND product.id IS NULL");
        $this->addSql("ALTER TABLE order_items ADD CONSTRAINT FK_ORDER_ITEMS_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE order_items DROP FOREIGN KEY FK_ORDER_ITEMS_PRODUCT");
    }
}
