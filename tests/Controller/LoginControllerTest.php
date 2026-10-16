<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use App\Domain\Identity\Entities\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testLegacyStartPageRedirectsToMigratedShippingOnTheCurrentHost(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $previousStartPage = $connection->fetchOne('SELECT start_page_id FROM identity_users WHERE id = 990001');
        $permissionId = $connection->fetchOne('SELECT id FROM identity_permissions WHERE legacy_division_id = 305');
        $hadPermission = (bool) $connection->fetchOne('SELECT 1 FROM identity_user_permissions WHERE user_id = 990001 AND permission_id = ?', [$permissionId]);

        try {
            $connection->update('identity_users', ['start_page_id' => 305], ['id' => 990001]);
            $connection->executeStatement('INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (990001, ?)', [$permissionId]);
            $client->getCookieJar()->clear();
            $client->request('GET', '/login', [], [], ['HTTP_HOST' => '127.0.0.1:8080']);
            $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'test-password']);

            self::assertResponseRedirects('/shipping');
            $client->request('GET', '/login');
            self::assertResponseRedirects('/shipping');
            $client->request('GET', '/');
            self::assertResponseRedirects('/login');
            $client->followRedirect();
            self::assertResponseRedirects('/shipping');
            $client->followRedirect();
            self::assertResponseIsSuccessful();
        } finally {
            $connection = self::getContainer()->get('doctrine.dbal.app_connection');
            $connection->update('identity_users', ['start_page_id' => $previousStartPage], ['id' => 990001]);
            if (!$hadPermission) {
                $connection->delete('identity_user_permissions', ['user_id' => 990001, 'permission_id' => $permissionId]);
            }
        }
    }

    public function testLoginCreatesOneServerSideSession(): void {
        $client = static::createClient();
        $this->authenticate($client);

        self::assertResponseRedirects("/enterprise");
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(User::DEFAULT_MAX_ACTIVE_SESSIONS, (int) self::getContainer()->get("doctrine.dbal.app_connection")->fetchOne("SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?", [990001]));
        self::assertSame("login_success", self::getContainer()->get("doctrine.dbal.app_connection")->fetchOne("SELECT event_type FROM identity_authentication_audit WHERE legacy_user_id = ? ORDER BY id DESC LIMIT 1", [990001]));
    }

    public function testRootRedirectsAuthenticatedUserWithoutStartPageToEnterprise(): void {
        $client = static::createClient();
        $this->authenticate($client);

        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertResponseRedirects('/enterprise');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testLogoutRequiresCsrfAndValidFormLogsOut(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $crawler = $client->followRedirect();
        $logoutForm = $crawler->filter('form[action="/logout"]')->form();

        $client->request("POST", "/logout");
        self::assertResponseStatusCodeSame(403);
        $client->request("GET", "/enterprise");
        self::assertResponseIsSuccessful();

        $client->submit($logoutForm);
        self::assertResponseRedirects("http://localhost/login");
        $client->request("GET", "/enterprise");
        self::assertResponseRedirects("http://localhost/login");
    }

    public function testNewBrowserReplacesOldSessionWithoutInvalidatingTheNewOne(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        foreach (range(1, 3) as $tab) {
            $client->request('GET', '/enterprise');
            self::assertResponseIsSuccessful();
        }
        $firstBrowserCookies = $client->getCookieJar()->all();
        $client->getCookieJar()->clear();
        $client->request('GET', '/login');
        $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'wrong-password']);
        self::assertResponseRedirects('http://localhost/login');
        $client->followRedirect();
        $client->getCookieJar()->clear();
        foreach ($firstBrowserCookies as $cookie) {
            $client->getCookieJar()->set($cookie);
        }
        $client->request('GET', '/enterprise');
        self::assertResponseIsSuccessful();
        $client->getCookieJar()->clear();
        $client->request('GET', '/login');
        $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'test-password']);
        self::assertResponseRedirects('/enterprise');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $newBrowserCookies = $client->getCookieJar()->all();
        $client->request('GET', '/enterprise');
        self::assertResponseIsSuccessful();
        $client->getCookieJar()->clear();
        foreach ($firstBrowserCookies as $cookie) {
            $client->getCookieJar()->set($cookie);
        }
        $client->request('GET', '/enterprise');
        self::assertResponseRedirects('/login');
        $client->getCookieJar()->clear();
        foreach ($newBrowserCookies as $cookie) {
            $client->getCookieJar()->set($cookie);
        }
        $client->request('GET', '/enterprise');
        self::assertResponseIsSuccessful();
        self::assertSame(1, (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = 990001'));
    }

    public function testIndividualLimitAllowsSeveralBrowsersAndLogoutFreesOnlyItsSlot(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        self::getContainer()->get('doctrine.dbal.app_connection')->update('identity_users', ['max_active_sessions' => 3], ['id' => 990001]);
        $browsers = [$client->getCookieJar()->all()];
        try {
            self::getContainer()->get('doctrine.dbal.app_connection')->update('identity_user_sessions', ['created_at' => '2026-10-08 00:00:00'], ['user_id' => 990001]);
            foreach (range(2, 3) as $browser) {
                $client->getCookieJar()->clear();
                $client->request('GET', '/login');
                $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'test-password']);
                self::assertResponseRedirects('/enterprise');
                $client->followRedirect();
                self::assertResponseIsSuccessful();
                $browsers[] = $client->getCookieJar()->all();
            }
            $client->getCookieJar()->clear();
            $client->request('GET', '/login');
            $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'test-password']);
            self::assertResponseRedirects('/enterprise');
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSame(3, (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = 990001'));

            foreach ($browsers as $index => $cookies) {
                $client->getCookieJar()->clear();
                foreach ($cookies as $cookie) {
                    $client->getCookieJar()->set($cookie);
                }
                $crawler = $client->request('GET', '/enterprise');
                if ($index === 0) {
                    self::assertResponseRedirects('/login');
                    continue;
                }
                self::assertResponseIsSuccessful();
                if ($index === 1) {
                    $client->submit($crawler->filter('form[action="/logout"]')->form());
                    self::assertResponseRedirects('http://localhost/login');
                    self::assertSame(2, (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = 990001'));
                }
            }
            $client->getCookieJar()->clear();
            $client->request('GET', '/login');
            $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'test-password']);
            self::assertResponseRedirects('/enterprise');
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSame(3, (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = 990001'));
        } finally {
            self::getContainer()->get('doctrine.dbal.app_connection')->update('identity_users', ['max_active_sessions' => User::DEFAULT_MAX_ACTIVE_SESSIONS], ['id' => 990001]);
            self::getContainer()->get('doctrine.dbal.app_connection')->delete('identity_user_sessions', ['user_id' => 990001]);
        }
    }

    public function testExpiredSessionRedirectsToLogin(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $connection->update('identity_user_sessions', ['expires_at' => (new \DateTimeImmutable('-1 second'))->format('Y-m-d H:i:s')], ['user_id' => 990001]);

        $client->request('GET', '/enterprise');
        self::assertResponseRedirects('/login');
    }

    public function testDisabledUserCannotLoginAndWrongPasswordDoesNotCreateSession(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get('doctrine.dbal.app_connection');
        $connection->update('identity_users', ['enabled' => 0], ['id' => 990001]);
        $connection->delete('identity_user_sessions', ['user_id' => 990001]);
        $client->getCookieJar()->clear();
        try {
            $client->request('GET', '/login');
            $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'test-password']);
            self::assertResponseRedirects('http://localhost/login');
            self::assertSame(0, (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = 990001'));
            self::getContainer()->get('doctrine.dbal.app_connection')->update('identity_users', ['enabled' => 1], ['id' => 990001]);
            $client->followRedirect();
            $client->submitForm('Вход', ['login' => 'test-user-990001', 'password' => 'wrong-password']);
            self::assertResponseRedirects('http://localhost/login');
            self::assertSame(0, (int) self::getContainer()->get('doctrine.dbal.app_connection')->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = 990001'));
        } finally {
            self::getContainer()->get('doctrine.dbal.app_connection')->update('identity_users', ['enabled' => 1], ['id' => 990001]);
        }
    }


}
