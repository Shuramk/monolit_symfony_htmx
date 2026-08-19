<?php declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class TestDatabaseIsolationTest extends KernelTestCase {
    public function testPhpunitCannotConnectToTheWorkingApplicationDatabase(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        self::assertSame('monolit_test', $db->fetchOne('SELECT DATABASE()'));
    }

    public function testBootstrapRejectsAnAccidentalWorkingDatabaseOverride(): void {
        $process = new Process([PHP_BINARY, '-r', 'require getcwd()."/tests/bootstrap.php";'], dirname(__DIR__, 2), [
            'APP_ENV'=>'test',
            'APP_DATABASE_URL'=>'mysql://guard:secret@127.0.0.1:3307/monolit_app',
        ]);
        $process->run();
        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('Tests may connect only to monolit_test', $process->getOutput().$process->getErrorOutput());
        self::assertStringNotContainsString('guard:secret', $process->getOutput().$process->getErrorOutput());
    }

    public function testDatabaseAccountCannotReadTheWorkingApplicationDatabase(): void {
        self::bootKernel();
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        self::assertSame('monolit_test', $db->fetchOne('SELECT DATABASE()'));
        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $db->fetchOne('SELECT COUNT(*) FROM monolit_app.identity_users');
    }
}
