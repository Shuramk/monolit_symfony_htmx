<?php declare(strict_types=1);

namespace App\Tests\Application\Middleware;

use App\Application\Middleware\CsrfTokenListener;
use App\Domain\Identity\Entities\User;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfTokenListenerTest extends TestCase {
    public function testEveryUnsafeMethodRequiresTokenWithoutControllerAttributes(): void {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method("isTokenValid");
        $listener = new CsrfTokenListener($this->security(), $manager);

        foreach (["POST", "PUT", "PATCH", "DELETE"] as $method) {
            try {
                $listener->onController($this->event(Request::create("/any-endpoint", $method)));
                self::fail($method." must require a CSRF token");
            } catch (InvalidCsrfTokenException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAcceptsFormJsonAndHeaderTokens(): void {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::exactly(3))->method("isTokenValid")->willReturnCallback(static function (CsrfToken $token): bool {
            self::assertSame("session", $token->getId());
            self::assertSame("valid-token", $token->getValue());

            return true;
        });
        $listener = new CsrfTokenListener($this->security(), $manager);

        $listener->onController($this->event(Request::create("/any-endpoint", "POST", ["_token" => "valid-token"])));
        $listener->onController($this->event(Request::create("/any-endpoint", "PUT", server: ["CONTENT_TYPE" => "application/json"], content: '{"_token":"valid-token"}')));
        $listener->onController($this->event(Request::create("/any-endpoint", "DELETE", server: ["HTTP_X_CSRF_TOKEN" => "valid-token"])));
    }

    public function testSafeAnonymousStatelessAndSubrequestsDoNotRequireSessionToken(): void {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method("isTokenValid");
        $listener = new CsrfTokenListener($this->security(), $manager);
        foreach (["GET", "HEAD", "OPTIONS"] as $method) {
            $listener->onController($this->event(Request::create("/any-endpoint", $method)));
        }
        $listener->onController($this->event(Request::create("/any-endpoint", "POST"), HttpKernelInterface::SUB_REQUEST));
        (new CsrfTokenListener($this->security(anonymous: true), $manager))->onController($this->event(Request::create("/any-endpoint", "POST")));
        (new CsrfTokenListener($this->security(stateless: true), $manager))->onController($this->event(Request::create("/any-endpoint", "POST")));
    }

    public function testInvalidTokenIsRejected(): void {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::once())->method("isTokenValid")->willReturn(false);
        $listener = new CsrfTokenListener($this->security(), $manager);
        $this->expectException(InvalidCsrfTokenException::class);

        $listener->onController($this->event(Request::create("/any-endpoint", "POST", ["_token" => "bad"])));
    }

    private function security(bool $anonymous = false, bool $stateless = false): Security {
        $security = $this->createStub(Security::class);
        $security->method("getFirewallConfig")->willReturn(new FirewallConfig("main", "checker", stateless: $stateless));
        $security->method("getUser")->willReturn($anonymous ? null : new User(19, "test", "Тест", null, true));

        return $security;
    }

    private function event(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): ControllerEvent {
        return new ControllerEvent($this->createStub(HttpKernelInterface::class), static fn () => null, $request, $requestType);
    }
}
