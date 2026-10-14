<?php

declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\ImportLegacyOrderManagersCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportLegacyOrderManagersCommandTest extends TestCase {
    public function testBackfillOnlyUpdatesMissingManagersAndNeverWritesToLegacy(): void {
        $legacy = $this->createMock(Connection::class);
        $app = $this->createMock(Connection::class);
        $legacy->expects(self::once())->method('fetchAllAssociative')->with('SELECT orderid, userID FROM sklad_orders WHERE userID > 0')->willReturn([['orderid' => 26010101, 'userID' => 7]]);
        $legacy->expects(self::never())->method('executeStatement');
        $legacy->expects(self::never())->method('insert');
        $legacy->expects(self::never())->method('delete');
        $app->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $operation): int => $operation());
        $app->expects(self::once())->method('executeStatement')->with('UPDATE orders SET manager_id = :manager WHERE id = :id AND manager_id IS NULL', ['manager' => 7, 'id' => 26010101])->willReturn(1);
        $app->expects(self::never())->method('delete');
        $tester = new CommandTester(new ImportLegacyOrderManagersCommand($legacy, $app));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Заполнены менеджеры заказов: 1', $tester->getDisplay());
    }
}
