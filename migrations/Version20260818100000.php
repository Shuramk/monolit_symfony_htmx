<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Creates the normalized application-owned Orders schema.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE orders (id BIGINT NOT NULL, customer_name VARCHAR(510) NOT NULL, customer_phone VARCHAR(64) DEFAULT NULL, shipping_address TEXT DEFAULT NULL, comments TEXT DEFAULT NULL, desired_at DATETIME NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(32) NOT NULL, prepayment NUMERIC(12, 2) NOT NULL DEFAULT 0, shipping_cost NUMERIC(12, 2) NOT NULL DEFAULT 0, markup SMALLINT NOT NULL DEFAULT 0, loyalty SMALLINT DEFAULT NULL, loyalty_comment TEXT DEFAULT NULL, imported_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), INDEX IDX_ORDERS_STATUS_DESIRED (status, desired_at), INDEX IDX_ORDERS_CUSTOMER (customer_name)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE order_items (id BIGINT AUTO_INCREMENT NOT NULL, order_id BIGINT NOT NULL, product_id BIGINT DEFAULT NULL, product_name VARCHAR(510) NOT NULL, unit VARCHAR(64) NOT NULL, quantity NUMERIC(12, 3) NOT NULL, unit_price NUMERIC(12, 4) NOT NULL, position INT NOT NULL, UNIQUE INDEX UNIQ_ORDER_ITEM_POSITION (order_id, position), INDEX IDX_ORDER_ITEMS_PRODUCT (product_id), PRIMARY KEY(id), CONSTRAINT FK_ORDER_ITEMS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE order_shipments (id BIGINT NOT NULL, order_id BIGINT NOT NULL, task_id BIGINT DEFAULT NULL, carrier_id BIGINT DEFAULT NULL, imported_at DATETIME NOT NULL, PRIMARY KEY(id), INDEX IDX_ORDER_SHIPMENTS_ORDER (order_id), CONSTRAINT FK_ORDER_SHIPMENTS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE order_shipment_items (shipment_id BIGINT NOT NULL, product_id BIGINT NOT NULL, quantity NUMERIC(12, 3) NOT NULL, PRIMARY KEY(shipment_id, product_id), CONSTRAINT FK_ORDER_SHIPMENT_ITEMS_SHIPMENT FOREIGN KEY (shipment_id) REFERENCES order_shipments (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE order_payments (source VARCHAR(24) NOT NULL, source_id BIGINT NOT NULL, order_id BIGINT NOT NULL, paid_at DATETIME NOT NULL, amount NUMERIC(12, 2) NOT NULL, comment TEXT DEFAULT NULL, user_id BIGINT DEFAULT NULL, shipment_id BIGINT DEFAULT NULL, imported_at DATETIME NOT NULL, PRIMARY KEY(source, source_id), INDEX IDX_ORDER_PAYMENTS_ORDER (order_id), CONSTRAINT FK_ORDER_PAYMENTS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE order_status_history (id BIGINT NOT NULL, order_id BIGINT NOT NULL, status VARCHAR(32) NOT NULL, changed_at DATETIME NOT NULL, comment VARCHAR(255) NOT NULL, user_id BIGINT DEFAULT NULL, task_id BIGINT DEFAULT NULL, imported_at DATETIME NOT NULL, PRIMARY KEY(id), INDEX IDX_ORDER_STATUS_HISTORY_ORDER (order_id, changed_at), CONSTRAINT FK_ORDER_STATUS_HISTORY_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE order_status_history");
        $this->addSql("DROP TABLE order_payments");
        $this->addSql("DROP TABLE order_shipment_items");
        $this->addSql("DROP TABLE order_shipments");
        $this->addSql("DROP TABLE order_items");
        $this->addSql("DROP TABLE orders");
    }
}
