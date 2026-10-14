<?php declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Common\ValueObjects\Decimal;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LegacyPaymentMetadataImporter {
    public function __construct(
        #[Autowire('@doctrine.dbal.legacy_connection')] private Connection $legacy,
        #[Autowire('@doctrine.dbal.app_connection')] private Connection $app,
    ) {}

    /** Updates only metadata; never recreates orders/payments or changes their amounts. */
    public function import(): array {
        return $this->app->transactional(function (): array {
            $pending = [];
            foreach ($this->app->fetchAllAssociative("SELECT source,source_id,order_id,amount,paid_at FROM order_payments WHERE source IN ('order','shipment') AND metadata_imported_at IS NULL FOR UPDATE") as $row) {
                $pending[$row['source'].':'.$row['source_id']] = $row;
            }
            $updated = 0;
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            foreach (['order'=>'sklad_payments','shipment'=>'sklad_payments_shipment'] as $source=>$table) {
                if ($pending === []) break;
                foreach ($this->legacy->iterateAssociative('SELECT paymentID,orderID,sumOfPayment,paymentDate,moneyTaken,toCard,pCashless FROM '.$table.' WHERE orderID IS NOT NULL ORDER BY paymentID') as $row) {
                    $key = $source.':'.$row['paymentID'];
                    $existing = $pending[$key] ?? null;
                    if ($existing === null) continue;
                    if ((int)$existing['order_id'] !== (int)$row['orderID']
                        || !Decimal::fromString((string)$existing['amount'],2)->equals(Decimal::fromString((string)$row['sumOfPayment'],2))
                        || $existing['paid_at'] !== $row['paymentDate']) {
                        throw new \DomainException('Платёж '.$key.' отличается от legacy. Перенос признаков остановлен без изменения данных.');
                    }
                    $updated += $this->app->executeStatement('UPDATE order_payments SET money_taken=?,to_card=?,payment_markup=?,metadata_imported_at=? WHERE source=? AND source_id=? AND metadata_imported_at IS NULL', [
                        $row['moneyTaken'], $row['toCard'], $row['pCashless'], $now, $source, $row['paymentID'],
                    ]);
                    unset($pending[$key]);
                }
            }
            if ($pending !== []) throw new \DomainException('Для '.count($pending).' импортированных оплат не найден исходный платёж. Перенос отменён.');
            return ['updated'=>$updated];
        });
    }
}
