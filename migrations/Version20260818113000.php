<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Adds concurrency-safe application order numbering and generated status-history identifiers.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE order_number_sequences (order_date CHAR(6) NOT NULL, last_number INT NOT NULL, PRIMARY KEY(order_date)) DEFAULT CHARACTER SET ascii COLLATE ascii_bin ENGINE = InnoDB");
        $this->addSql("ALTER TABLE order_status_history MODIFY id BIGINT NOT NULL AUTO_INCREMENT");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE order_status_history MODIFY id BIGINT NOT NULL");
        $this->addSql("DROP TABLE order_number_sequences");
    }
}
