<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductCatalogControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testCatalogRequiresItsOwnLegacyPermissionAndReturnsAnHtmxFragment(): void {
        $client = static::createClient();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 1010");
        $connection->executeStatement("DELETE FROM identity_user_permissions WHERE user_id = ? AND permission_id = ?", [990001, $permissionId]);
        $client->request("GET", "/products");
        self::assertResponseStatusCodeSame(403);

        $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);

        $client->request("GET", "/products?q=%D0%9A%D0%B8%D1%80%D0%BF%D0%B8%D1%87", [], [], ["HTTP_HX-Request" => "true"]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("Кирпич", $client->getResponse()->getContent());
    }
}
