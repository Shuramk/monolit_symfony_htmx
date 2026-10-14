<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class IdentityUserProvider implements UserProviderInterface {
    public function __construct(private UserRepository $repository) {
    }

    public function loadUserByIdentifier(string $identifier): User {
        $user = $this->repository->findByLogin($identifier);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): User {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException("Unsupported user class.");
        }

        $refreshed = $this->repository->findById($user->getId());

        if ($refreshed === null) {
            throw new UserNotFoundException();
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool {
        return is_a($class, User::class, true);
    }
}
