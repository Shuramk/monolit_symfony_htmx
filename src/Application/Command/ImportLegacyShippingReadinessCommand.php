<?php declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name:'legacy:import-shipping-readiness', description:'Перенос готовности активных задач на отгрузку без перезаписи новых значений.')]
final class ImportLegacyShippingReadinessCommand extends Command {
    public function __construct(
        #[Autowire('@doctrine.dbal.legacy_connection')] private readonly Connection $legacy,
        #[Autowire('@doctrine.dbal.app_connection')] private readonly Connection $app,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $ids = $this->app->fetchFirstColumn("SELECT id FROM warehouse_shipping_tasks WHERE type=2 AND status=0 AND JSON_CONTAINS_PATH(payload,'one','$.collected')=0");
        $count = 0;
        if ($ids !== []) {
            $readiness = $this->legacy->fetchAllKeyValue('SELECT taskID,MAX(taskCollected) FROM sklad_tasks_shipment_collected WHERE taskID IN (?) GROUP BY taskID', [$ids], [ArrayParameterType::INTEGER]);
            $count = $this->app->transactional(function () use ($ids, $readiness): int {
                $updated = 0;
                foreach ($ids as $id) {
                    $updated += $this->app->executeStatement("UPDATE warehouse_shipping_tasks SET payload=JSON_SET(payload,'$.collected',CAST(? AS UNSIGNED)) WHERE id=? AND type=2 AND status=0 AND JSON_CONTAINS_PATH(payload,'one','$.collected')=0", [(int)($readiness[$id] ?? 0), $id]);
                }

                return $updated;
            });
        }
        (new SymfonyStyle($input, $output))->success('Перенесена готовность задач: '.$count.'.');

        return self::SUCCESS;
    }
}
