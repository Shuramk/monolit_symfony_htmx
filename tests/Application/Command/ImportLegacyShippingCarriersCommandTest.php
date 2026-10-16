<?php declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\ImportLegacyShippingCarriersCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportLegacyShippingCarriersCommandTest extends KernelTestCase {
    public function testImportIsRepeatableAndPreservesLocalChanges(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            $tester = new CommandTester(self::getContainer()->get(ImportLegacyShippingCarriersCommand::class));
            self::assertSame(0, $tester->execute([]));
            $id = (int) $db->fetchOne('SELECT id FROM shipping_carriers ORDER BY id LIMIT 1');
            self::assertGreaterThan(0, $id);
            $db->update('shipping_carriers', ['name' => 'Локальное имя'], ['id' => $id]);
            self::assertSame(0, $tester->execute([]));
            self::assertStringContainsString('перевозчиков: 0', $tester->getDisplay());
            self::assertSame('Локальное имя', $db->fetchOne('SELECT name FROM shipping_carriers WHERE id=:id', ['id' => $id]));
        } finally {
            $db->rollBack();
        }
    }
}
