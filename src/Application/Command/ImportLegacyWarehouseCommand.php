<?php declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Warehouse\Import\LegacyWarehouseImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'legacy:import-warehouse', description: 'Однократно переносит складскую историю, задачи, рецепты и сырьё; не меняет существующие остатки.')]
final class ImportLegacyWarehouseCommand extends Command {
    public function __construct(private readonly LegacyWarehouseImporter $importer) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $result = $this->importer->import();
        $io = new SymfonyStyle($input, $output);
        foreach ($result['counts'] as $table => $count) $io->writeln($table.': '.$count);
        $io->success($result['alreadyImported'] ? 'Уже импортировано. Новые данные не изменены.' : 'Склад импортирован. Legacy и текущие остатки не изменены.');
        return Command::SUCCESS;
    }
}
