<?php declare(strict_types=1);

namespace App\Tests\Domain\Identity\Import;

use App\Application\Command\ImportLegacyIdentityCommand;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LegacyUserRoleImportTest extends KernelTestCase {
    public function testImportAndReimportConvertLegacyNumbersWithoutChangingTheRoleModel(): void {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            $command = $container->get(ImportLegacyIdentityCommand::class);
            $import = new \ReflectionMethod($command, 'importUser');
            $users = $container->get(UserRepository::class);
            $fixture = ['user_id'=>99009060, 'user_login'=>'role-import-fixture', 'user_name'=>'Role import', 'user_priv'=>0, 'user_password'=>'legacy-hash', 'password_hash'=>null, 'password_hash_updated_at'=>null];
            foreach ([1=>UserRole::Main,2=>UserRole::Sales,3=>UserRole::Production,10=>UserRole::ShiftSupervisor,99=>UserRole::Partner,1000=>null,0=>null,777=>null] as $group=>$role) {
                $import->invoke($command, $fixture + ['groupID'=>$group], '2026-10-11 18:00:00');
                $user = $users->findById(99009060);
                self::assertSame($role, $user->getRole());
                self::assertSame($role?->value, $db->fetchOne('SELECT role FROM identity_users WHERE id=99009060'));
                self::assertSame($group, (int)$db->fetchOne('SELECT legacy_group_id FROM identity_users WHERE id=99009060'));
                self::assertSame($role !== null, $user->canAccess());
            }
        } finally {
            $db->rollBack();
            $em->clear();
        }
    }
}
