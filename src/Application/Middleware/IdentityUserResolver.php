<?php declare(strict_types=1);

namespace App\Application\Middleware;

use App\Domain\Identity\Entities\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class IdentityUserResolver implements ValueResolverInterface {
    public function __construct(private TokenStorageInterface $tokenStorage) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable {
        if ($argument->getType() !== User::class) {
            return [];
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof User) {
            return [$user];
        }

        if ($user === null && $argument->isNullable()) {
            return [null];
        }

        throw new AccessDeniedException("Требуется авторизация.");
    }
}
