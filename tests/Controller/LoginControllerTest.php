<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\IdentityTestUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase {
    use IdentityTestUser;

    public function testLoginCreatesOneServerSideSession(): void {
        $client = static::createClient();
        $this->authenticate($client);

        self::assertResponseRedirects("/enterprise");
        self::assertSame(1, (int) self::getContainer()->get("doctrine.dbal.app_connection")->fetchOne("SELECT COUNT(*) FROM identity_user_sessions WHERE user_id = ?", [990001]));
        self::assertSame("login_success", self::getContainer()->get("doctrine.dbal.app_connection")->fetchOne("SELECT event_type FROM identity_authentication_audit WHERE legacy_user_id = ? ORDER BY id DESC LIMIT 1", [990001]));
    }


}
