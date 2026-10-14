<?php declare(strict_types=1);

namespace App\Tests\Domain\Identity\Security;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Import\LegacyUserRoleMapper;
use App\Domain\Identity\Security\IdentityUserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserRoleAccessTest extends TestCase {
    public function testMissingAndUnknownRolesCannotLogin(): void {
        foreach ([0,1000,999] as $role) {
            $user = new User(1,'test','Test',null,true);
            $user->setRole(LegacyUserRoleMapper::fromGroupId($role));
            self::assertNull($user->getRole());
            self::assertSame([],$user->getRoles());
            try { new IdentityUserChecker()->checkPreAuth($user); self::fail('Role must be denied'); }
            catch (CustomUserMessageAccountStatusException $exception) { self::assertStringContainsString('роль',$exception->getMessage()); }
        }
    }

    public function testKnownRolesHaveNoImplicitAdministratorHierarchy(): void {
        foreach (UserRole::cases() as $role) {
            $user = new User(1,'test','Test',null,true);
            $user->setRole($role);
            self::assertSame(['ROLE_USER',$role->value],$user->getRoles());
            new IdentityUserChecker()->checkPreAuth($user);
        }
    }

    public function testLegacyNumbersAreConvertedOnlyAtTheImportBoundary(): void {
        foreach ([1=>UserRole::Main,2=>UserRole::Sales,3=>UserRole::Production,10=>UserRole::ShiftSupervisor,99=>UserRole::Partner] as $id=>$role) {
            self::assertSame($role,LegacyUserRoleMapper::fromGroupId($id));
        }
        self::assertNull(LegacyUserRoleMapper::fromGroupId(1000));
        self::assertNull(LegacyUserRoleMapper::fromGroupId(0));
        self::assertNull(LegacyUserRoleMapper::fromGroupId(999));
    }
}
