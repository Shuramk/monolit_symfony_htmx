<?php

declare(strict_types=1);

namespace App\Domain\Identity\Audit;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

final readonly class LoginFailureSubscriber implements EventSubscriberInterface {
    public function __construct(private AuthenticationAuditRepository $authenticationAuditRepository) {
    }

    public static function getSubscribedEvents(): array {
        return [LoginFailureEvent::class => "onLoginFailure"];
    }

    public function onLoginFailure(LoginFailureEvent $event): void {
        $login = trim((string) $event->getRequest()->request->get("login"));
        $this->authenticationAuditRepository->record("login_failure", null, $login !== "" ? $login : null, $event->getRequest());
    }
}
