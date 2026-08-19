<?php declare(strict_types=1);

namespace App\Tests\Domain\Identity\Import;

use App\Application\Command\ImportLegacyIdentityCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LegacyStartPageImportTest extends KernelTestCase {
    public function testStartPagesOnlyImportDoesNotReplaceUsersRightsOrSessions(): void {
        self::bootKernel(); $container = self::getContainer();
        $db = $container->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            $counts = static fn (): array => array_map(static fn ($table): int => (int)$db->fetchOne('SELECT COUNT(*) FROM '.$table),['identity_users','identity_user_permissions','identity_user_sessions']);
            $before = $counts();
            $profile = $db->fetchAssociative('SELECT login,display_name,role,enabled,password_hash,updated_at FROM identity_users WHERE id=1');
            $tester = new CommandTester($container->get(ImportLegacyIdentityCommand::class));
            self::assertSame(0,$tester->execute(['--start-pages-only'=>true]));
            self::assertSame($before,$counts());
            self::assertSame($profile,$db->fetchAssociative('SELECT login,display_name,role,enabled,password_hash,updated_at FROM identity_users WHERE id=1'));
            self::assertGreaterThan(0,(int)$db->fetchOne('SELECT COUNT(*) FROM identity_users WHERE start_page_id IS NOT NULL'));
            self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM identity_users u WHERE u.start_page_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM identity_user_permissions up JOIN identity_permissions p ON p.id=up.permission_id JOIN identity_legacy_divisions d ON d.id=p.legacy_division_id WHERE up.user_id=u.id AND d.id=u.start_page_id AND d.enabled=1)'));
        } finally { $db->rollBack(); }
    }
}
