<?php declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Warehouse\Repository\ShippingImportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'legacy:import-shipping-carriers', description: 'Добавляет перевозчиков без перезаписи существующих записей.')]
final class ImportLegacyShippingCarriersCommand extends Command {
    public function __construct(private readonly ShippingImportRepository $repository) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $count = $this->repository->importCarriers();
        (new SymfonyStyle($input, $output))->success('Добавлено перевозчиков: ' . $count . '.');

        return self::SUCCESS;
    }
}
