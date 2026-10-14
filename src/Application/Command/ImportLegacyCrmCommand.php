<?php declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Crm\Import\LegacyCrmImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name:'legacy:import-crm',description:'Одноразовый перенос CRM без чата из read-only legacy.')]
final class ImportLegacyCrmCommand extends Command {
    public function __construct(private readonly LegacyCrmImporter $importer) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input,$output);
        try { $result = $this->importer->import(); } catch (\DomainException $e) { $io->error($e->getMessage()); return self::FAILURE; }
        $io->success(json_encode($result,JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
