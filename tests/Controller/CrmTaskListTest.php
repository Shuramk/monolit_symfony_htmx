<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CrmTaskListTest extends WebTestCase {
    use IdentityTestUser;

    public function testReadModelKeepsFiltersPaginationAndRouteControlledAccess(): void {
        $client = self::createClient();
        $client->disableReboot();
        $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection');
        $db->beginTransaction();
        try {
            foreach ([1750, 1754] as $permission) {
                $db->executeStatement(
                    'INSERT IGNORE INTO identity_user_permissions(user_id, permission_id) '
                        .'SELECT 990001, id FROM identity_permissions WHERE legacy_division_id=?',
                    [$permission],
                );
            }
            $base = [
                'author_id' => 990001,
                'executor_id' => 990001,
                'created_at' => '2026-10-13 10:00:00',
                'due_at' => '2026-10-14 10:00:00',
                'director_comment' => '',
                'last_comment' => '',
            ];
            for ($index = 0; $index < 35; ++$index) {
                $db->insert('crm_tasks', $base + ['text' => 'Task DTO fixture '.$index]);
            }
            $db->insert('crm_tasks', array_replace($base, [
                'author_id' => 1, 'executor_id' => 1, 'text' => 'Task DTO fixture чужая',
            ]));
            $db->insert('crm_tasks', $base + [
                'text' => 'Task DTO fixture завершена', 'completed_at' => '2026-10-13 12:00:00',
            ]);
            $query = ['q' => 'Task DTO fixture', 'executor' => '', 'author' => '', 'page' => '2'];
            $crawler = $client->request('GET', '/crm/tasks', $query);
            self::assertResponseIsSuccessful();
            self::assertCount(5, $crawler->filter('.crm-list tbody tr'));
            self::assertStringNotContainsString('чужая', $crawler->filter('.crm-list')->text());
            self::assertSame('Task DTO fixture', $crawler->filter('input[name="q"]')->attr('value'));

            $crawler = $client->request('GET', '/crm/tasks', array_replace($query, [
                'page' => '1', 'executor' => '990001', 'author' => '990001',
                'scope' => 'from-me', 'director' => '1', 'archive' => '1', 'permission' => '1755',
            ]));
            self::assertResponseIsSuccessful();
            self::assertCount(30, $crawler->filter('.crm-list tbody tr'));
            self::assertStringNotContainsString('чужая', $crawler->filter('.crm-list')->text());
            self::assertStringNotContainsString('завершена', $crawler->filter('.crm-list')->text());
            self::assertSame('990001', $crawler->filter('select[name="executor"] option[selected]')->attr('value'));
            self::assertSame('from-me', $crawler->filter('select[name="scope"] option[selected]')->attr('value'));

            $client->request('GET', '/crm/director/tasks', $query);
            self::assertResponseStatusCodeSame(403);
            $crawler = $client->request('GET', '/crm/tasks/archive', [
                'q' => 'Task DTO fixture', 'state' => 'completed', 'executor' => '990001',
            ]);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('завершена', $crawler->filter('.crm-list')->text());
            self::assertCount(1, $crawler->filter('.crm-list tbody tr'));

            foreach ([1755, 1759] as $permission) {
                $db->executeStatement(
                    'INSERT IGNORE INTO identity_user_permissions(user_id, permission_id) '
                        .'SELECT 990001, id FROM identity_permissions WHERE legacy_division_id=?',
                    [$permission],
                );
            }
            $crawler = $client->request('GET', '/crm/director/tasks', [
                'q' => 'Task DTO fixture чужая', 'director' => '0',
            ]);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('чужая', $crawler->filter('.crm-list')->text());
            $client->request('GET', '/crm/director/tasks/archive', [
                'q' => 'Task DTO fixture', 'state' => 'completed',
            ]);
            self::assertResponseIsSuccessful();
            $client->request('GET', '/crm/tasks', ['scope' => 'invalid']);
            self::assertResponseStatusCodeSame(400);
        } finally {
            $db->rollBack();
        }
    }
}
