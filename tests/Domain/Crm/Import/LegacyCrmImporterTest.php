<?php declare(strict_types=1);

namespace App\Tests\Domain\Crm\Import;

use App\Domain\Crm\Import\LegacyCrmImporter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class LegacyCrmImporterTest extends TestCase {
    private string $directory;

    protected function setUp(): void {
        $this->directory = sys_get_temp_dir().'/monolit-crm-import-'.bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->directory.'/project');
    }

    protected function tearDown(): void {
        (new Filesystem())->remove($this->directory);
    }

    public static function sourceDirectories(): iterable {
        yield 'configured directory' => [true];
        yield 'legacy local default' => [false];
    }

    #[DataProvider('sourceDirectories')]
    public function testCopiesFilesWithoutChangingSourceAndReportsMissingAttachments(bool $configured): void {
        $source = $this->directory.($configured ? '/old-site/taskfiles' : '/monolit_old/taskfiles');
        $filesystem = new Filesystem();
        $filesystem->dumpFile($source.'/42/document.txt','Original attachment');
        $legacy = $this->createMock(Connection::class);
        $legacy->expects(self::never())->method('insert');
        $legacy->expects(self::never())->method('executeStatement');
        $legacy->method('fetchAllAssociative')->willReturnCallback(static fn (string $sql): array =>
            $sql === 'SELECT * FROM sklad_crm_tasks ORDER BY id' ? [[
                'id'=>42,'userID'=>1,'executor'=>2,'date'=>'2026-10-16 12:00:00',
                'execute_date'=>null,'text'=>'Task','director_comment'=>null,'last_comment'=>null,
                'completion_date'=>null,'cancelled'=>0,'hidden'=>0,'co_executors'=>null,'observers'=>null,
                'files'=>'["document.txt","missing.txt","../unsafe.txt"]',
            ]] : []
        );
        $app = $this->createStub(Connection::class);
        $app->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $app->method('fetchOne')->willReturn(false);
        $files = [];
        $app->method('insert')->willReturnCallback(static function (string $table, array $data) use (&$files): int {
            if ($table === 'crm_task_files') $files[] = $data;
            return 1;
        });

        $result = (new LegacyCrmImporter($legacy,$app,$this->directory.'/project',$configured ? $source.'/' : null))->import();

        self::assertSame(['deals'=>0,'tasks'=>1,'orphanContacts'=>0,'missingFiles'=>1],$result);
        self::assertCount(2,$files);
        self::assertSame(1,$files[0]['available']);
        self::assertSame(strlen('Original attachment'),$files[0]['size']);
        self::assertSame(0,$files[1]['available']);
        self::assertSame(0,$files[1]['size']);
        self::assertSame('Original attachment',file_get_contents($source.'/42/document.txt'));
        self::assertSame('Original attachment',file_get_contents($this->directory.'/project/var/crm-files/'.$files[0]['storage_name']));
        self::assertFileDoesNotExist($this->directory.'/project/var/crm-files/'.$files[1]['storage_name']);
    }

    public function testInvalidConfiguredDirectoryDoesNotSaveImportMarkerOrReadLegacy(): void {
        $legacy = $this->createMock(Connection::class);
        $legacy->expects(self::never())->method('fetchAllAssociative');
        $app = $this->createMock(Connection::class);
        $app->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $app->method('fetchOne')->willReturn(false);
        $app->expects(self::never())->method('insert');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('LEGACY_CRM_FILES_DIR');
        (new LegacyCrmImporter($legacy,$app,$this->directory.'/project',$this->directory.'/missing'))->import();
    }

    public function testCompletedImportDoesNotReadSourceOrCopyFilesAgain(): void {
        $legacy = $this->createMock(Connection::class);
        $legacy->expects(self::never())->method('fetchAllAssociative');
        $app = $this->createMock(Connection::class);
        $app->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $app->method('fetchOne')->willReturn(1);
        $app->expects(self::never())->method('insert');

        self::assertSame(['alreadyImported'=>true],(new LegacyCrmImporter(
            $legacy,$app,$this->directory.'/project',$this->directory.'/missing',
        ))->import());
        self::assertDirectoryDoesNotExist($this->directory.'/project/var/crm-files');
    }
}
