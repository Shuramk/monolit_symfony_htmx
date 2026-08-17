<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class IdentityUserChecker implements UserCheckerInterface {
    public function checkPreAuth(UserInterface $user): void {
        if (!$user instanceof IdentityUser || !$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException("Учётная запись отключена.");
        }
    }

    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void {
    }
}
