<?php

declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'legacy:import-order-managers', description: 'Fills missing order managers without rebuilding orders or their history.')]
final class ImportLegacyOrderManagersCommand extends Command {
    public function __construct(private readonly Connection $legacy, private readonly Connection $app) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $managers = $this->legacy->fetchAllAssociative('SELECT orderid, userID FROM sklad_orders WHERE userID > 0');
        $count = $this->app->transactional(function () use ($managers): int {
            $count = 0;
            foreach ($managers as $manager) {
                $count += $this->app->executeStatement('UPDATE orders SET manager_id = :manager WHERE id = :id AND manager_id IS NULL', ['manager' => (int) $manager['userID'], 'id' => (int) $manager['orderid']]);
            }

            return $count;
        });
        $output->writeln(sprintf('Заполнены менеджеры заказов: %d. Состав, суммы и история не изменены.', $count));

        return Command::SUCCESS;
    }
}
