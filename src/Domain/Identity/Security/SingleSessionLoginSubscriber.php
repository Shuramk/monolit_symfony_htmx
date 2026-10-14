<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Audit\AuthenticationAuditRepository;
use App\Domain\Identity\Repository\IdentitySessionRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class SingleSessionLoginSubscriber implements EventSubscriberInterface {
    public function __construct(
        private IdentitySessionRepository $identitySessionRepository,
        private AuthenticationAuditRepository $authenticationAuditRepository,
    ) {
    }

    public static function getSubscribedEvents(): array {
        return [LoginSuccessEvent::class => ['onLoginSuccess', -100]];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void {
        $user = $event->getAuthenticatedToken()->getUser();
        $request = $event->getRequest();

        if (!$user instanceof User || !$request->hasSession()) {
            return;
        }

        // Register only after all authentication checks and Symfony's session ID migration.
        $this->identitySessionRepository->register($user->getId(), $request->getSession()->getId(), new \DateTimeImmutable());
        $this->authenticationAuditRepository->record("login_success", $user->getId(), $user->getUserIdentifier(), $request);
    }
}
