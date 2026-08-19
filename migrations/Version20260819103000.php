<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Stores the product-group snapshot on every order item.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE order_items ADD product_group_name VARCHAR(510) DEFAULT NULL AFTER product_name");
        $this->addSql("UPDATE order_items item INNER JOIN products product ON product.id = item.product_id LEFT JOIN products parent ON parent.id = product.parent_id SET item.product_group_name = parent.name WHERE item.product_group_name IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE order_items DROP product_group_name");
    }
}
