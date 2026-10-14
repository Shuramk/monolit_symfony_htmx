<?php declare(strict_types=1);

namespace App\Tests\Application\Middleware;

use App\Application\Middleware\IdentityUserResolver;
use App\Domain\Identity\Entities\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class IdentityUserResolverTest extends TestCase {
    public function testReturnsCurrentUserByTypeWithoutAttribute(): void {
        $user = new User(19, "test", "Тест", null, true);
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, "main", $user->getRoles()));
        $resolver = new IdentityUserResolver($storage);

        $values = $resolver->resolve(new Request(), new ArgumentMetadata("actor", User::class, false, false, null));

        self::assertSame([$user], $values);
        self::assertSame([], $resolver->resolve(new Request(), new ArgumentMetadata("service", \stdClass::class, false, false, null)));
    }

    public function testRequiredUserRejectsAnonymousRequest(): void {
        $this->expectException(AccessDeniedException::class);

        (new IdentityUserResolver(new TokenStorage()))->resolve(new Request(), new ArgumentMetadata("actor", User::class, false, false, null));
    }

    public function testNullableUserAllowsAnonymousRequest(): void {
        $values = (new IdentityUserResolver(new TokenStorage()))->resolve(new Request(), new ArgumentMetadata("actor", User::class, false, false, null, true));

        self::assertSame([null], $values);
    }
}
