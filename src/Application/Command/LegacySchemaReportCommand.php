<?php

namespace App\Application\Command;

use App\Legacy\Schema\SchemaInventoryFormatter;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'legacy:schema-report', description: 'Builds a read-only inventory of the legacy MariaDB schema.')]
final class LegacySchemaReportCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaInventoryFormatter $formatter,
        #[Autowire("%kernel.project_dir%")] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Markdown output path relative to the project.', 'docs/legacy/schema-inventory.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tables = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT table_name, engine, table_collation, table_rows
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
            ORDER BY table_name
            SQL);

        $relativePath = $input->getOption('output');
        $path = $this->projectDir.'/'.ltrim($relativePath, '/');
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create output directory "%s".', $directory));
        }

        file_put_contents($path, $this->formatter->render($tables));

        (new SymfonyStyle($input, $output))->success(sprintf('Read-only report for %d tables written to %s.', count($tables), $relativePath));

        return Command::SUCCESS;
    }
}
