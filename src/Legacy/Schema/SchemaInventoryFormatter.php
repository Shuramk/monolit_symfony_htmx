<?php

namespace App\Legacy\Schema;

final class SchemaInventoryFormatter
{
    /**
     * @param list<array{table_name: string, engine: string|null, table_collation: string|null, table_rows: int|string|null}> $tables
     */
    public function render(array $tables): string
    {
        $lines = [
            '# Legacy schema inventory',
            '',
            'Этот отчёт сформирован только чтением метаданных MariaDB.',
            'Количество строк является оценкой `information_schema` для InnoDB.',
            '',
            '| Таблица | Engine | Collation | Строк (оценка) | Стратегия |',
            '| --- | --- | --- | ---: | --- |',
        ];

        foreach ($tables as $table) {
            $collation = $table['table_collation'] ?? '—';
            $strategy = str_starts_with($collation, 'utf8mb4_')
                ? 'совместимо с целевой кодировкой'
                : '⚠️ explicit conversion';

            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s |',
                $table['table_name'],
                $table['engine'] ?? '—',
                $collation,
                $table['table_rows'] ?? '—',
                $strategy,
            );
        }

        return implode("\n", $lines)."\n";
    }
}
