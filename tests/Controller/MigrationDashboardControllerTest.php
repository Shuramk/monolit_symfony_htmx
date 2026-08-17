<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MigrationDashboardControllerTest extends WebTestCase
{
    public function testDashboardRendersMigrationBoundary(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Monolit → Symfony + HTMX');
        self::assertSelectorExists('[hx-get="/migration/status"]');
    }

    public function testStatusFragmentRendersWithoutDatabaseAccess(): void
    {
        $client = static::createClient();
        $client->request('GET', '/migration/status');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('strong', 'Контур запущен.');
    }
}
