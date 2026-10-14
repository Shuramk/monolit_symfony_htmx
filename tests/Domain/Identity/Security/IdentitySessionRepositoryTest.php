<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity\Security;

use App\Domain\Identity\Repository\IdentitySessionRepository;
use App\Domain\Identity\Entities\User;
use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class IdentitySessionRepositoryTest extends KernelTestCase {
    use IdentityTestUser;

    public function testNewSessionReplacesPreviousSessionAtDefaultLimit(): void {
        self::bootKernel();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        $now = new \DateTimeImmutable();
        $connection->executeStatement("INSERT INTO identity_users (id, login, display_name, role, legacy_group_id, legacy_user_priv, enabled, imported_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)", [990003, "session-user", "Session user", "ROLE_MAIN", 1, 0, 1, $now->format("Y-m-d H:i:s"), $now->format("Y-m-d H:i:s")]);
        $repository = self::getContainer()->get(IdentitySessionRepository::class);
        $connection->delete('identity_user_sessions', ['user_id' => 990003]);

        $repository->register(990003, "first-session", $now);
        try {
            $repository->register(990003, "second-session", $now);
            self::assertFalse($repository->isCurrent(990003, "first-session", $now));
            self::assertTrue($repository->isCurrent(990003, "second-session", $now));
            self::assertSame(User::DEFAULT_MAX_ACTIVE_SESSIONS, (int) $connection->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?', [990003]));
        } finally {
            $connection->delete('identity_user_sessions', ['user_id' => 990003]);
        }
    }

    public function testIndividualLimitLogoutAndExpirationAreIndependent(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $now = new \DateTimeImmutable('2026-10-08 10:00:00');
            $user = new User(99002003, 'multi-session-test', 'Several sessions', null, true, $now);
            $user->setMaxActiveSessions(3);
            $em->persist($user);
            $em->flush();
            $repository = self::getContainer()->get(IdentitySessionRepository::class);
            $repository->register($user->getId(), 'first', $now);
            $repository->register($user->getId(), 'second', $now->modify('+10 minutes'));
            $repository->register($user->getId(), 'third', $now->modify('+20 minutes'));
            $repository->register($user->getId(), 'third', $now->modify('+20 minutes'));
            self::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?', [$user->getId()]), 'The same browser does not consume another slot.');
            self::assertTrue($repository->isCurrent($user->getId(), 'first', $now->modify('+25 minutes')));
            $repository->register($user->getId(), 'fourth', $now->modify('+30 minutes'));
            self::assertFalse($repository->isCurrent($user->getId(), 'first', $now->modify('+30 minutes')), 'Evict the oldest login, not the least recently active session.');
            self::assertTrue($repository->isCurrent($user->getId(), 'second', $now->modify('+30 minutes')));
            $repository->remove($user->getId(), 'second');
            self::assertFalse($repository->isCurrent($user->getId(), 'second', $now->modify('+30 minutes')));
            self::assertTrue($repository->isCurrent($user->getId(), 'third', $now->modify('+30 minutes')));
            self::assertTrue($repository->isCurrent($user->getId(), 'fourth', $now->modify('+30 minutes')));
            $repository->register($user->getId(), 'fifth', $now->modify('+40 minutes'));
            self::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?', [$user->getId()]));
            $repository->register($user->getId(), 'sixth', $now->modify('+90 minutes'));
            self::assertFalse($repository->isCurrent($user->getId(), 'third', $now->modify('+90 minutes')));
            self::assertFalse($repository->isCurrent($user->getId(), 'fourth', $now->modify('+90 minutes')));
            self::assertTrue($repository->isCurrent($user->getId(), 'fifth', $now->modify('+90 minutes')), 'Expired sessions free slots without evicting a live session.');
            self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?', [$user->getId()]));
            $user->setMaxActiveSessions(1);
            $em->flush();
            $repository->register($user->getId(), 'seventh', $now->modify('+95 minutes'));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?', [$user->getId()]));
            self::assertFalse($repository->isCurrent($user->getId(), 'fifth', $now->modify('+95 minutes')));
            self::assertFalse($repository->isCurrent($user->getId(), 'sixth', $now->modify('+95 minutes')));
            self::assertTrue($repository->isCurrent($user->getId(), 'seventh', $now->modify('+95 minutes')));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }

    public function testIdleTimeoutSlidesOnActivityAndExpiresAtSixtyMinutes(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $now = new \DateTimeImmutable('2026-10-08 10:00:00');
            $em->persist(new User(99002002, 'orm-session-test', 'Session timeout test', null, true, $now));
            $em->flush();
            $repository = self::getContainer()->get(IdentitySessionRepository::class);
            $repository->register(99002002, 'same-browser', $now);
            self::assertSame('2026-10-08 11:00:00', $connection->fetchOne('SELECT expires_at FROM identity_user_sessions WHERE user_id = 99002002'));
            self::assertTrue($repository->isCurrent(99002002, 'same-browser', $now->modify('+59 minutes')));
            self::assertSame('2026-10-08 11:59:00', $connection->fetchOne('SELECT expires_at FROM identity_user_sessions WHERE user_id = 99002002'));
            self::assertTrue($repository->isCurrent(99002002, 'same-browser', $now->modify('+118 minutes')));
            self::assertFalse($repository->isCurrent(99002002, 'same-browser', $now->modify('+178 minutes')));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }

    public function testConcurrentLoginsCannotExceedTheLimit(): void {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $userId = 99002004;
        $connection->delete('identity_users', ['id' => $userId]);
        $em->persist(new User($userId, 'concurrent-session-test', 'Concurrent sessions', null, true));
        $em->flush();

        $worker = <<<'PHP'
            require getcwd().'/tests/bootstrap.php';
            $kernel = new App\Kernel('test', true);
            $kernel->boot();
            $repository = new App\Domain\Identity\Repository\IdentitySessionRepository($kernel->getContainer()->get('doctrine')->getConnection('app'));
            fwrite(STDOUT, "ready\n");
            $repository->register((int) $argv[1], $argv[2], new DateTimeImmutable());
            fwrite(STDOUT, "accepted\n");
            PHP;
        $processes = [];
        $connection->beginTransaction();
        try {
            $connection->fetchOne('SELECT id FROM identity_users WHERE id = ? FOR UPDATE', [$userId]);
            foreach (['first-browser', 'second-browser'] as $sessionId) {
                $process = new Process([PHP_BINARY, '-r', $worker, (string) $userId, $sessionId], self::getContainer()->getParameter('kernel.project_dir'), ['APP_ENV' => 'test']);
                $process->setTimeout(10);
                $process->start();
                $processes[] = $process;
                self::assertTrue($process->waitUntil(static fn (string $type, string $output): bool => $type === Process::OUT && str_contains($output, 'ready')), $process->getErrorOutput());
            }
            $connection->commit();
            $results = [];
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = trim(str_replace("ready\n", '', $process->getOutput()));
            }
            sort($results);
            self::assertSame(['accepted', 'accepted'], $results);
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?', [$userId]));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
            $connection->delete('identity_users', ['id' => $userId]);
            $em->clear();
        }
    }
}
