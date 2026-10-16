<?php declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Warehouse\Repository\ShippingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:repair-shipping-history-timezone', description: 'Исправляет UTC-время журнала отгрузок на Europe/Kyiv без изменения legacy-истории.')]
final class RepairShippingHistoryTimezoneCommand extends Command {
    public function __construct(private readonly ShippingRepository $repository) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Применить исправление. Без опции выполняется только проверка.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $apply = (bool) $input->getOption('apply');
        $count = $this->repository->repairHistoryTimezone($apply);
        (new SymfonyStyle($input, $output))->success(
            ($apply ? 'Исправлено записей: ' : 'Найдено записей для исправления: ') . $count . '.',
        );

        return self::SUCCESS;
    }
}
