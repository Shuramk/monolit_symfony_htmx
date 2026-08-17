<?php

namespace App\Tests\Legacy\Schema;

use App\Legacy\Schema\SchemaInventoryFormatter;
use PHPUnit\Framework\TestCase;

final class SchemaInventoryFormatterTest extends TestCase
{
    public function testItMarksLegacyCharacterSetsForExplicitMigration(): void
    {
        $report = (new SchemaInventoryFormatter())->render([
            [
                'table_name' => 'sklad_enterprise',
                'engine' => 'InnoDB',
                'table_collation' => 'utf8mb3_general_ci',
                'table_rows' => 1,
            ],
            [
                'table_name' => 'sklad_carriers',
                'engine' => 'InnoDB',
                'table_collation' => 'cp1251_general_ci',
                'table_rows' => 12,
            ],
        ]);

        self::assertStringContainsString('| `sklad_enterprise` | InnoDB | utf8mb3_general_ci | 1 | ⚠️ explicit conversion |', $report);
        self::assertStringContainsString('| `sklad_carriers` | InnoDB | cp1251_general_ci | 12 | ⚠️ explicit conversion |', $report);
        self::assertStringContainsString('Этот отчёт сформирован только чтением метаданных MariaDB.', $report);
    }
}
