<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MigrationDashboardControllerTest extends WebTestCase {
    public function testRootRedirectsGuestToLogin(): void {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="login"]');
    }

    public function testStatusFragmentRendersWithoutDatabaseAccess(): void {
        $client = static::createClient();
        $client->request('GET', '/migration/status');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('strong', 'Контур запущен.');
    }
}
