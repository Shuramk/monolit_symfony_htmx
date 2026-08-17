<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Support\IdentityTestUser;

final class EnterpriseProfileControllerTest extends WebTestCase {
    use IdentityTestUser;
    public function testEnterpriseProfileRendersTheImportedProfile(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists("h1");
        self::assertSelectorExists("a[href=\"/enterprise/warranty\"]");
    }

    public function testWarrantyRendersTheSanitizedImportedHtml(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $client->request("GET", "/enterprise/warranty");

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains("h1", "Гарантийный талон");
        self::assertSelectorTextContains(".enterprise-warranty", "10-ЛЕТНЮЮ ГАРАНТИЮ");
    }
}
