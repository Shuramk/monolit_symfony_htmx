<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserSettingsControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testRemovingRoleInvalidatesAnAlreadyAuthenticatedSession(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            self::getContainer()->get(\App\Domain\Identity\UseCases\SaveUser::class)->execute(990001,new \App\Domain\Identity\UseCases\Dtos\SaveUserDto(null,permissions:[['id'=>'220']],startPageId:220));
            $client->request('GET','/enterprise');
            self::assertResponseRedirects('/login');
            self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id=990001'));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testEnabledAccountWithoutRoleCannotLoginWithCorrectPassword(): void {
        $client = static::createClient(); $client->disableReboot();
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            $db->insert('identity_users',['id'=>99009042,'login'=>'no-role-login','display_name'=>'No role','legacy_group_id'=>1000,'legacy_user_priv'=>0,'enabled'=>1,'password_hash'=>password_hash('correct-password',PASSWORD_ARGON2ID),'imported_at'=>'2026-10-11 12:00:00','updated_at'=>'2026-10-11 12:00:00']);
            $client->request('GET','/login'); $client->submitForm('Вход',['login'=>'no-role-login','password'=>'correct-password']);
            self::assertResponseRedirects('/login'); $client->followRedirect();
            self::assertStringContainsString('не назначена роль',$client->getResponse()->getContent());
            self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id=99009042'));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testRolesAndNumericPermissionsCanBeEditedAndRemovingRoleClosesAccess(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            foreach ([1001,1002,1003] as $permission) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$permission]);
            $db->insert('identity_users',['id'=>99009041,'login'=>'role-target','display_name'=>'Role target','role'=>'ROLE_SALES','max_active_sessions'=>3,'legacy_group_id'=>2,'legacy_user_priv'=>0,'enabled'=>1,'imported_at'=>'2026-10-11 12:00:00','updated_at'=>'2026-10-11 12:00:00']);
            $client->request('GET','/settings/users'); self::assertResponseIsSuccessful();
            $crawler = $client->request('GET','/settings/users/99009041'); self::assertResponseIsSuccessful();
            self::assertSelectorExists('select[name="role"] option[value=""]');
            self::assertSelectorNotExists('[name="maxActiveSessions"]');
            $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('input[name="_token"]')->attr('value')];
            $payload = ['role'=>'ROLE_PRODUCTION','permissions'=>[['id'=>'220'],['id'=>'227']],'startPageId'=>220];
            $client->jsonRequest('POST','/settings/users/99009041',$payload); self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST','/settings/users/99009041',$payload,$headers); self::assertResponseIsSuccessful();
            self::assertSame('ROLE_PRODUCTION',$db->fetchOne('SELECT role FROM identity_users WHERE id=99009041'));
            self::assertSame(3,(int)$db->fetchOne('SELECT max_active_sessions FROM identity_users WHERE id=99009041'));
            $client->jsonRequest('POST','/settings/users/99009041',$payload + ['maxActiveSessions'=>99],$headers);
            self::assertResponseIsSuccessful();
            self::assertSame(3,(int)$db->fetchOne('SELECT max_active_sessions FROM identity_users WHERE id=99009041'));
            $access = self::getContainer()->get(\App\Domain\Identity\Repository\IdentityMenuRepository::class);
            self::assertTrue($access->userHasDivision(99009041,227)); self::assertFalse($access->userHasDivision(99009041,228));
            $client->jsonRequest('POST','/settings/users/99009041',array_replace($payload,['role'=>'ROLE_UNKNOWN']),$headers); self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/settings/users/99009041',array_replace($payload,['permissions'=>[['id'=>'987654321']]]),$headers); self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/settings/users/99009041',array_replace($payload,['role'=>'']),$headers); self::assertResponseIsSuccessful();
            self::assertFalse($access->userHasDivision(99009041,227)); self::assertSame([],$access->getForUserId(99009041,'admin.php'));
            $db->executeStatement('DELETE up FROM identity_user_permissions up JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=990001 AND p.legacy_division_id=1003');
            $client->jsonRequest('POST','/settings/users/99009041',$payload,$headers); self::assertResponseStatusCodeSame(403);
            self::assertNull($db->fetchOne('SELECT role FROM identity_users WHERE id=99009041'));
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }

    public function testListHidesInactiveUsersByDefaultAndKeepsTheFilterState(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $db = self::getContainer()->get('doctrine.dbal.app_connection'); $db->beginTransaction();
        try {
            $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=1001');
            foreach ([99009043=>1,99009044=>0] as $id=>$enabled) {
                $db->insert('identity_users',['id'=>$id,'login'=>'filter-user-'.$id,'display_name'=>'Filter user '.$id,'role'=>'ROLE_SALES','legacy_user_priv'=>0,'enabled'=>$enabled,'imported_at'=>'2026-10-11 12:00:00','updated_at'=>'2026-10-11 12:00:00']);
            }
            $crawler = $client->request('GET','/settings/users'); self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[type="checkbox"][name="hideInactive"][checked]');
            self::assertSelectorExists('.user-settings-table');
            self::assertSelectorTextContains('thead','Логин');
            self::assertSelectorTextContains('thead','Роль');
            self::assertStringNotContainsString('сессий',$crawler->filter('thead')->text());
            self::assertStringContainsString('Filter user 99009043',$crawler->filter('tbody')->text());
            self::assertStringNotContainsString('Filter user 99009044',$crawler->filter('tbody')->text());
            $crawler = $client->request('GET','/settings/users?hideInactive=0'); self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('input[type="checkbox"][name="hideInactive"][checked]');
            self::assertStringContainsString('Filter user 99009044',$crawler->filter('tbody')->text());
            $client->submit($crawler->filter('.user-settings-filter')->form(['hideInactive'=>'1'])); self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('Filter user 99009044',$client->getCrawler()->filter('tbody')->text());
        } finally { $db->rollBack(); self::getContainer()->get('doctrine.orm.entity_manager')->clear(); }
    }
}
