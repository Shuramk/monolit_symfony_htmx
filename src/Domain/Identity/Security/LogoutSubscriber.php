<?php declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Entities\User;

use App\Domain\Identity\Repository\IdentitySessionRepository;
use App\Domain\Identity\Audit\AuthenticationAuditRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class LogoutSubscriber implements EventSubscriberInterface {
    public function __construct(private IdentitySessionRepository $identitySessionRepository, private AuthenticationAuditRepository $authenticationAuditRepository) {
    }

    public static function getSubscribedEvents(): array {
        // Read the current ID before Symfony invalidates the PHP session.
        return [LogoutEvent::class => ['onLogout', 64]];
    }

    public function onLogout(LogoutEvent $event): void {
        $user = $event->getToken()->getUser();

        if ($user instanceof User && $event->getRequest()->hasSession()) {
            $this->identitySessionRepository->remove($user->getId(), $event->getRequest()->getSession()->getId());
            $this->authenticationAuditRepository->record("logout", $user->getId(), $user->getUserIdentifier(), $event->getRequest());
        }
    }
}
