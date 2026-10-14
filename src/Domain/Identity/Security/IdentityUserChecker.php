<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Entities\User;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class IdentityUserChecker implements UserCheckerInterface {
    public function checkPreAuth(UserInterface $user): void {
        if (!$user instanceof User || !$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException("Учётная запись отключена.");
        }
        if (!$user->canAccess()) throw new CustomUserMessageAccountStatusException('Пользователю не назначена роль. Доступ закрыт.');
    }

    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void {
    }
}
