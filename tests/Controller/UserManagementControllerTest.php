<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserManagementControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testCreateEditPasswordAccessAndStartPageAreSavedAtomically(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $em = self::getContainer()->get('doctrine.orm.entity_manager'); $db = $em->getConnection(); $db->beginTransaction();
        try {
            foreach ([1001,1002,1003,1050] as $right) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            $client->request('GET','/settings/users'); self::assertSelectorExists('a[href="/settings/users/new"]');
            $crawler = $client->request('GET','/settings/users/new'); self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[name="displayName"]'); self::assertSelectorExists('input[name="login"]');
            self::assertSelectorExists('input[name="password"][required]'); self::assertSelectorNotExists('[name="maxActiveSessions"]');
            self::assertSelectorExists('.user-permissions .user-permissions-role select[name="role"]');
            self::assertSelectorNotExists('.user-profile-fields select[name="role"]');
            self::assertSelectorExists('[data-permission-row="100"]');
            self::assertSelectorNotExists('.user-permission-section[data-expanded="true"]');
            self::assertSelectorNotExists('.user-permission-child:not([hidden])');
            self::assertSelectorExists('[data-permission-toggle="100"][aria-expanded="false"]');
            self::assertSelectorExists('[data-permission-row="227"][data-parent="220"][hidden]');
            self::assertSelectorExists('[data-permission-row="227"].user-permission-child[data-expanded="true"] .user-permission-title[style="--permission-depth:1"]');
            self::assertSelectorNotExists('[data-permission-row="220"] [data-permission-toggle]');
            self::assertSelectorExists('[data-permission-row="100"].user-permission-section .user-permission-title[style="--permission-depth:0"]');
            self::assertSelectorNotExists('[name="startPageId"][value="227"]');
            self::assertSelectorNotExists('[name="startPageId"][value="0"]');
            self::assertSelectorTextContains('[data-start-page-label]','не выбрана');
            self::assertSelectorExists('button[type="submit"][disabled]');
            self::assertSelectorExists('[name="startPageId"][required]');
            $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('input[name="_token"]')->attr('value')];
            $payload = ['displayName'=>'Созданный пользователь','login'=>'created-user-fixture','role'=>'ROLE_SALES','enabled'=>true,'password'=>'new-password-123','passwordConfirmation'=>'new-password-123','startPageId'=>220,'permissions'=>[['id'=>'200'],['id'=>'220']]];
            $count = (int)$db->fetchOne('SELECT COUNT(*) FROM identity_users');
            $client->jsonRequest('POST','/settings/users/new',$payload); self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST','/settings/users/new',array_replace($payload,['passwordConfirmation'=>'mismatch']),$headers); self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/settings/users/new',array_replace($payload,['password'=>'short','passwordConfirmation'=>'short']),$headers); self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/settings/users/new',array_replace($payload,['password'=>'','passwordConfirmation'=>'']),$headers); self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/settings/users/new',array_replace($payload,['startPageId'=>101]),$headers); self::assertResponseStatusCodeSame(400);
            self::assertSame($count,(int)$db->fetchOne('SELECT COUNT(*) FROM identity_users'));
            foreach ([null,0,-1] as $page) {
                $client->jsonRequest('POST','/settings/users/new',array_replace($payload,['startPageId'=>$page]),$headers); self::assertResponseStatusCodeSame(400);
            }
            $withoutPage = $payload; unset($withoutPage['startPageId']);
            $client->jsonRequest('POST','/settings/users/new',$withoutPage,$headers); self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/settings/users/new',$payload,$headers); self::assertResponseIsSuccessful();
            $row = $db->fetchAssociative('SELECT * FROM identity_users WHERE login=?',[$payload['login']]); $id = (int)$row['id'];
            self::assertSame('Созданный пользователь',$row['display_name']); self::assertSame('ROLE_SALES',$row['role']);
            self::assertSame(220,(int)$row['start_page_id']); self::assertSame(1,(int)$row['max_active_sessions']);
            self::assertTrue(password_verify('new-password-123',$row['password_hash'])); self::assertStringStartsWith('$argon2id$', $row['password_hash']);
            self::assertSame('/settings/users/'.$id,json_decode($client->getResponse()->getContent(),true)['location']);
            $client->jsonRequest('POST','/settings/users/new',$payload,$headers); self::assertResponseStatusCodeSame(400);
            $client->jsonRequest('POST','/settings/users/new',array_replace($payload,['login'=>strtoupper($payload['login'])]),$headers); self::assertResponseStatusCodeSame(400);
            $crawler = $client->request('GET','/settings/users/'.$id); self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('.user-permission-section[data-expanded="true"]');
            self::assertSelectorNotExists('.user-permission-child:not([hidden])');
            self::assertSelectorNotExists('[name="password"][value]'); self::assertSelectorExists('[name="startPageId"][value="220"][checked]');
            self::assertSelectorTextContains('[data-start-page-label]','Заказы');
            self::assertSelectorNotExists('button[type="submit"][disabled]');
            $url = '/settings/users/'.$id;
            foreach ([null,0] as $page) {
                $client->jsonRequest('POST',$url,array_replace($payload,['startPageId'=>$page]),$headers); self::assertResponseStatusCodeSame(400);
                self::assertSame(220,(int)$db->fetchOne('SELECT start_page_id FROM identity_users WHERE id=?',[$id]));
            }
            $client->jsonRequest('POST',$url,$withoutPage,$headers); self::assertResponseStatusCodeSame(400);
            $edit = array_replace($payload,['login'=>'renamed-user-fixture','displayName'=>'Новое имя','password'=>'','passwordConfirmation'=>'']);
            $client->jsonRequest('POST',$url,$edit,$headers); self::assertResponseIsSuccessful();
            self::assertSame($row['password_hash'],$db->fetchOne('SELECT password_hash FROM identity_users WHERE id=?',[$id]));
            $db->update('identity_users',['max_active_sessions'=>3],['id'=>$id]);
            self::getContainer()->get(\App\Domain\Identity\Repository\IdentitySessionRepository::class)->register($id,'existing-fixture-session',new \DateTimeImmutable());
            $edit = array_replace($edit,['password'=>'changed-password-123','passwordConfirmation'=>'changed-password-123']);
            $client->jsonRequest('POST',$url,$edit,$headers); self::assertResponseIsSuccessful();
            self::assertTrue(password_verify('changed-password-123',$db->fetchOne('SELECT password_hash FROM identity_users WHERE id=?',[$id])));
            self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM identity_user_sessions WHERE user_id=?',[$id]));
            self::assertSame(3,(int)$db->fetchOne('SELECT max_active_sessions FROM identity_users WHERE id=?',[$id]));
            $client->jsonRequest('POST',$url,array_replace($edit,['login'=>'test-user-990001']),$headers); self::assertResponseStatusCodeSame(400);
            self::assertSame('renamed-user-fixture',$db->fetchOne('SELECT login FROM identity_users WHERE id=?',[$id]));
            $edit = array_replace($edit,['password'=>'','passwordConfirmation'=>'','enabled'=>false]);
            $client->jsonRequest('POST',$url,$edit,$headers); self::assertResponseIsSuccessful();
            self::assertSame(0,(int)$db->fetchOne('SELECT enabled FROM identity_users WHERE id=?',[$id]));
            $edit['enabled'] = true;
            $client->jsonRequest('POST',$url,$edit,$headers); self::assertResponseIsSuccessful();
            $client->getCookieJar()->clear();
            $client->request('GET','/login'); $client->submitForm('Вход',['login'=>'renamed-user-fixture','password'=>'new-password-123']); self::assertResponseRedirects('/login');
            $client->request('GET','/login'); $client->submitForm('Вход',['login'=>'renamed-user-fixture','password'=>'changed-password-123']); self::assertResponseRedirects('/orders');
            $db->executeStatement('DELETE up FROM identity_user_permissions up JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=? AND p.legacy_division_id=220',[$id]);
            $client->getCookieJar()->clear(); $client->request('GET','/login'); $client->submitForm('Вход',['login'=>'renamed-user-fixture','password'=>'changed-password-123']); self::assertResponseRedirects('/enterprise');
        } finally { $db->rollBack(); $em->clear(); }
    }

    public function testCreateAndPasswordChangesRequireTheirOwnPermissions(): void {
        $client = static::createClient(); $client->disableReboot(); $this->authenticate($client);
        $em = self::getContainer()->get('doctrine.orm.entity_manager'); $db = $em->getConnection(); $db->beginTransaction();
        try {
            foreach ([1001,1002,1003] as $right) $db->executeStatement('INSERT IGNORE INTO identity_user_permissions(user_id,permission_id) SELECT 990001,id FROM identity_permissions WHERE legacy_division_id=?',[$right]);
            $db->executeStatement('DELETE up FROM identity_user_permissions up JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=990001 AND p.legacy_division_id=1050');
            $crawler = $client->request('GET','/settings/users/990001'); self::assertResponseIsSuccessful(); self::assertSelectorNotExists('[name="password"]');
            $headers = ['HTTP_X_CSRF_TOKEN'=>$crawler->filter('input[name="_token"]')->attr('value')];
            $client->jsonRequest('POST','/settings/users/990001',['role'=>'ROLE_MAIN','startPageId'=>220,'password'=>'forbidden-password','passwordConfirmation'=>'forbidden-password'],$headers); self::assertResponseStatusCodeSame(403);
            $db->executeStatement('DELETE up FROM identity_user_permissions up JOIN identity_permissions p ON p.id=up.permission_id WHERE up.user_id=990001 AND p.legacy_division_id=1003');
            $client->request('GET','/settings/users'); self::assertSelectorNotExists('a[href="/settings/users/new"]');
            $client->request('GET','/settings/users/new'); self::assertResponseStatusCodeSame(403);
            $client->jsonRequest('POST','/settings/users/new',['role'=>'ROLE_SALES','startPageId'=>220],$headers); self::assertResponseStatusCodeSame(403);
        } finally { $db->rollBack(); $em->clear(); }
    }
}
