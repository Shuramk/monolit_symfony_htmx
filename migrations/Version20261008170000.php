<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008170000 extends AbstractMigration {
    public function getDescription(): string {
        return 'Warehouse operations, production, recipes and immutable legacy history; preserves existing balances.';
    }

    public function up(Schema $schema): void {
        $tables = [
            'warehouse_imports' => 'id VARCHAR(40) NOT NULL, completed_at DATETIME DEFAULT NULL, counts LONGTEXT DEFAULT NULL, PRIMARY KEY(id)',
            'warehouse_history' => 'id BIGINT AUTO_INCREMENT NOT NULL, product_id BIGINT NOT NULL, user_id BIGINT DEFAULT NULL, shift_id INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, action VARCHAR(255) NOT NULL, quantity DECIMAL(12,3) NOT NULL, storage INT NOT NULL DEFAULT 2, comment TEXT DEFAULT NULL, manufacturing_date DATETIME DEFAULT NULL, task_id BIGINT DEFAULT NULL, operation_key VARCHAR(100) DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id), UNIQUE KEY UNIQ_WAREHOUSE_OPERATION(operation_key), INDEX IDX_WAREHOUSE_HISTORY_PRODUCT(product_id,created_at,id)',
            'warehouse_shipping_tasks' => 'id BIGINT AUTO_INCREMENT NOT NULL, order_id BIGINT DEFAULT NULL, status INT NOT NULL, type INT NOT NULL, performance_date DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, comment TEXT DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_WAREHOUSE_SHIPPING_OPEN(type,status,id), INDEX IDX_WAREHOUSE_SHIPPING_ORDER(order_id)',
            'warehouse_shipping_items' => 'id BIGINT AUTO_INCREMENT NOT NULL, task_id BIGINT NOT NULL, product_id BIGINT NOT NULL, quantity DECIMAL(12,3) NOT NULL, price DECIMAL(12,4) NOT NULL DEFAULT 0, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_WAREHOUSE_SHIPPING_PRODUCT(product_id,task_id), INDEX IDX_WAREHOUSE_SHIPPING_TASK(task_id)',
            'production_tasks' => 'id BIGINT AUTO_INCREMENT NOT NULL, product_id BIGINT NOT NULL, quantity DECIMAL(12,3) NOT NULL, press_id INT NOT NULL, sorting INT NOT NULL DEFAULT 0, done TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME DEFAULT NULL, due_at DATETIME DEFAULT NULL, user_id BIGINT DEFAULT NULL, comment TEXT DEFAULT NULL, operation_key VARCHAR(100) DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id), UNIQUE KEY UNIQ_PRODUCTION_OPERATION(operation_key), INDEX IDX_PRODUCTION_OPEN(done,sorting,id)',
            'production_entries' => 'id BIGINT AUTO_INCREMENT NOT NULL, task_id BIGINT DEFAULT NULL, product_id BIGINT DEFAULT NULL, quantity DECIMAL(12,3) DEFAULT NULL, press_id INT DEFAULT NULL, shift_id INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, user_id BIGINT DEFAULT NULL, accepted_at DATETIME DEFAULT NULL, accepted_by BIGINT DEFAULT NULL, comment TEXT DEFAULT NULL, operation_key VARCHAR(100) DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id), UNIQUE KEY UNIQ_PRODUCTION_ENTRY_OPERATION(operation_key), INDEX IDX_PRODUCTION_COVER(product_id,accepted_by), INDEX IDX_PRODUCTION_ENTRY_TASK(task_id)',
            'raw_materials' => 'id BIGINT NOT NULL, name VARCHAR(255) NOT NULL, unit VARCHAR(255) DEFAULT NULL, ratio DECIMAL(15,6) DEFAULT NULL, production TINYINT(1) NOT NULL DEFAULT 0, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id)',
            'raw_material_history' => 'id BIGINT AUTO_INCREMENT NOT NULL, material_id BIGINT NOT NULL, quantity DECIMAL(15,3) NOT NULL, created_at DATETIME DEFAULT NULL, user_id BIGINT DEFAULT NULL, price DECIMAL(15,5) DEFAULT NULL, comment TEXT DEFAULT NULL, warehouse_history_id BIGINT DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_RAW_MATERIAL_HISTORY(material_id,created_at), INDEX IDX_RAW_MATERIAL_OPERATION(warehouse_history_id)',
            'raw_material_balances' => 'id BIGINT AUTO_INCREMENT NOT NULL, material_id BIGINT NOT NULL, balance DECIMAL(15,3) NOT NULL, created_at DATETIME DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id)',
            'production_recipes' => 'id BIGINT NOT NULL, product_id BIGINT NOT NULL, press_id INT NOT NULL, output_quantity DECIMAL(15,6) DEFAULT NULL, components LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_PRODUCTION_RECIPE(product_id,press_id,id)',
            'warehouse_shift_days' => 'id INT NOT NULL, attendance LONGTEXT DEFAULT NULL, forms LONGTEXT DEFAULT NULL, payload LONGTEXT DEFAULT NULL, PRIMARY KEY(id)',
        ];
        foreach ($tables as $name => $definition) {
            $this->addSql("CREATE TABLE $name ($definition) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
        }
        $this->addSql("INSERT INTO warehouse_imports (id) VALUES ('legacy-v1')");
    }

    public function down(Schema $schema): void {
        foreach (['warehouse_shift_days','production_recipes','raw_material_balances','raw_material_history','raw_materials','production_entries','production_tasks','warehouse_shipping_items','warehouse_shipping_tasks','warehouse_history','warehouse_imports'] as $name) {
            $this->addSql('DROP TABLE '.$name);
        }
    }
}
