<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Entities\Product;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductCatalogControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testCatalogRequiresItsOwnPermissionAndReturnsAnHtmxFragment(): void {
        $client = static::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $client->followRedirect();
        $connection = self::getContainer()->get("doctrine.dbal.app_connection");
        assert($connection instanceof Connection);
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection->beginTransaction();
        try {
            $now = new \DateTimeImmutable('2026-10-16 12:00:00');
            $group = new Product(899005, 'Группа HTTP-каталога', 'шт', Decimal::zero(4), $now);
            $name = 'Кирпич HTTP fixture 899006';
            $variant = new Product(899006, $name, 'шт', Decimal::fromString('12.3456', 4), $now, $group);
            $em->persist($group);
            $em->persist($variant);
            $em->flush();
            $countBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM products');
            $productBefore = $connection->fetchAssociative('SELECT * FROM products WHERE id = 899006');

            $permissionId = $connection->fetchOne("SELECT id FROM identity_permissions WHERE legacy_division_id = 1010");
            $connection->executeStatement("DELETE FROM identity_user_permissions WHERE user_id = ? AND permission_id = ?", [990001, $permissionId]);
            $client->request("GET", "/products");
            self::assertResponseStatusCodeSame(403);
            $connection->executeStatement("INSERT IGNORE INTO identity_user_permissions (user_id, permission_id) VALUES (?, ?)", [990001, $permissionId]);

            $client->request('GET', '/products?q=' . rawurlencode($name), [], [], ['HTTP_HX-Request' => 'true']);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'tr');
            self::assertSelectorTextContains('tr td:nth-child(2)', $name);
            self::assertStringNotContainsString('<html', $client->getResponse()->getContent());
            self::assertSame($countBefore, (int) $connection->fetchOne('SELECT COUNT(*) FROM products'));
            self::assertSame($productBefore, $connection->fetchAssociative('SELECT * FROM products WHERE id = 899006'));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }
}
