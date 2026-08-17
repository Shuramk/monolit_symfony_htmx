<?php declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Repository\IdentitySessionRepository;
use App\Domain\Identity\Audit\AuthenticationAuditRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class LogoutSubscriber implements EventSubscriberInterface {
    public function __construct(private IdentitySessionRepository $identitySessionRepository, private AuthenticationAuditRepository $authenticationAuditRepository) {
    }

    public static function getSubscribedEvents(): array {
        return [LogoutEvent::class => "onLogout"];
    }

    public function onLogout(LogoutEvent $event): void {
        $user = $event->getToken()->getUser();

        if ($user instanceof IdentityUser) {
            $this->identitySessionRepository->remove($user->getId());
            $this->authenticationAuditRepository->record("logout", $user->getId(), $user->getUserIdentifier(), $event->getRequest());
        }
    }
}
