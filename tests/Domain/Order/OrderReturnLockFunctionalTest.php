<?php declare(strict_types=1);

namespace App\Tests\Domain\Order;

use App\Domain\Order\Repository\OrderRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderReturnLockFunctionalTest extends KernelTestCase {
    public function testOrderLockSerializesCompetingOperationsUntilTheTransactionFinishes(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $other = DriverManager::getConnection($db->getParams());
        $other->executeStatement('SET SESSION innodb_lock_wait_timeout=1');
        $id = (int) $db->fetchOne('SELECT id FROM orders ORDER BY id LIMIT 1');
        self::assertGreaterThan(0, $id);
        $db->beginTransaction();
        try {
            self::getContainer()->get(OrderRepository::class)->findForUpdate($id);
            $other->beginTransaction();
            try {
                $other->fetchOne('SELECT id FROM orders WHERE id=? FOR UPDATE', [$id]);
                self::fail('A competing operation must wait for the order lock.');
            } catch (LockWaitTimeoutException) {
                self::assertTrue($db->isTransactionActive());
            } finally {
                $other->rollBack();
            }
        } finally {
            $db->rollBack();
            self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        }
        $other->beginTransaction();
        try {
            self::assertSame($id, (int) $other->fetchOne('SELECT id FROM orders WHERE id=? FOR UPDATE', [$id]));
        } finally {
            $other->rollBack();
            $other->close();
        }
    }
}
