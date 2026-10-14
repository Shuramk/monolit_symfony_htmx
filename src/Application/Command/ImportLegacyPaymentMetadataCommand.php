<?php declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Order\Services\LegacyPaymentMetadataImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'legacy:import-payment-metadata', description: 'Восстанавливает признаки денег, карты и БН только в существующих импортированных платежах; не меняет суммы и заказы.')]
final class ImportLegacyPaymentMetadataCommand extends Command {
    public function __construct(private readonly LegacyPaymentMetadataImporter $importer) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $result = $this->importer->import();
        (new SymfonyStyle($input,$output))->success('Перенесены признаки '.$result['updated'].' оплат. Суммы, заказы и legacy не изменены.');
        return Command::SUCCESS;
    }
}
