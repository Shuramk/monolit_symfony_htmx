<?php declare(strict_types=1);

namespace App\Tests\Domain\Identity\Entities;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Repository\UserRepository;
use App\Domain\Identity\Security\IdentityUserProvider;
use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserMappingTest extends KernelTestCase {
    public function testMappingMatchesExistingTable(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager = $em->getConnection()->createSchemaManager();
        $schemaEm = new EntityManager($em->getConnection(), $em->getConfiguration(), new EventManager());
        $schema = (new SchemaTool($schemaEm))->getSchemaFromMetadata([$schemaEm->getClassMetadata(User::class)]);
        $table = $manager->introspectTable('identity_users');
        // Import metadata is intentionally not part of the User entity.
        $table->dropColumn('legacy_group_id');
        $diff = $manager->createComparator()->compareTables($table, $schema->getTable('identity_users'));

        self::assertTrue($diff->isEmpty(), implode("\n", $em->getConnection()->getDatabasePlatform()->getAlterTableSQL($diff)));
        self::assertSame(1, User::DEFAULT_MAX_ACTIVE_SESSIONS);
        self::assertSame(3600, User::SESSION_IDLE_TIMEOUT_SECONDS);
        self::assertSame(User::SESSION_IDLE_TIMEOUT_SECONDS, self::getContainer()->getParameter('session.storage.options')['gc_maxlifetime']);
    }

    public function testRepositoryPersistsUserAndProviderRefreshesEntity(): void {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $now = new \DateTimeImmutable('2026-10-08 14:15:16');
            $user = new User(99002001, 'orm-user-test', 'ORM пользователь', 'test-password-hash', true, $now);
            self::assertSame(User::DEFAULT_MAX_ACTIVE_SESSIONS, $user->getMaxActiveSessions());
            $user->setMaxActiveSessions(3);
            $user->setRole(UserRole::Sales);
            $repository = $container->get(UserRepository::class);
            $repository->save($user);
            $em->clear();
            $loaded = $repository->findById($user->getId());
            self::assertInstanceOf(User::class, $loaded);
            self::assertInstanceOf(UserInterface::class, $loaded);
            self::assertInstanceOf(PasswordAuthenticatedUserInterface::class, $loaded);
            self::assertSame('test-password-hash', $loaded->getPassword());
            self::assertSame('ORM пользователь', $loaded->getDisplayName());
            self::assertTrue($loaded->isEnabled());
            self::assertSame(3, $loaded->getMaxActiveSessions());
            self::assertSame(UserRole::Sales, $loaded->getRole());
            self::assertSame('ROLE_SALES', $connection->fetchOne('SELECT role FROM identity_users WHERE id = ?', [$user->getId()]));
            $provider = $container->get(IdentityUserProvider::class);
            self::assertSame($loaded, $provider->loadUserByIdentifier('orm-user-test'));
            self::assertTrue($provider->supportsClass(User::class));
            self::assertFalse($provider->supportsClass(\stdClass::class));
            $sessionUser = unserialize(serialize($loaded));
            $connection->update('identity_users', ['display_name' => 'Новое имя', 'enabled' => 0, 'password_hash' => 'updated-password-hash', 'role' => null], ['id' => $user->getId()]);
            $refreshed = $provider->refreshUser($sessionUser);
            self::assertSame('Новое имя', $refreshed->getDisplayName());
            self::assertFalse($refreshed->isEnabled());
            self::assertSame('updated-password-hash', $refreshed->getPassword());
            self::assertNull($refreshed->getRole());
            self::assertNull($repository->findById(999999999));
            self::assertNull($repository->findByLogin('missing-orm-user'));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }

    public function testSessionLimitMustBePositive(): void {
        $user = new User(1, 'user', 'User', null, true);
        $this->expectException(\InvalidArgumentException::class);
        $user->setMaxActiveSessions(0);
    }
}
