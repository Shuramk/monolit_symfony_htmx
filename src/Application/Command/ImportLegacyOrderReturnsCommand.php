<?php declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Order\Repository\OrderReturnImportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'legacy:import-order-returns', description: 'Однократный перенос возвратов и заявок без повторного изменения склада и кассы.')]
final class ImportLegacyOrderReturnsCommand extends Command {
    public function __construct(private readonly OrderReturnImportRepository $repository) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $report = $this->repository->import();
        $io = new SymfonyStyle($input, $output);
        $io->table(['Показатель', 'Значение'], array_map(static fn ($key, $value): array => [$key, is_array($value) ? implode(', ', $value) : (string) (int) $value], array_keys($report), array_values($report)));
        $io->success($report['alreadyImported'] ? 'Импорт уже выполнен. Данные не изменены.' : 'Перенос завершён. Остатки и касса не изменялись.');
        return self::SUCCESS;
    }
}
