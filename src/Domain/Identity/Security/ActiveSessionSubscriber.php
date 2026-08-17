<?php declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Repository\IdentitySessionRepository;
use App\Domain\Identity\Audit\AuthenticationAuditRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class ActiveSessionSubscriber implements EventSubscriberInterface {
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private IdentitySessionRepository $identitySessionRepository,
        private UrlGeneratorInterface $urlGenerator,
        private AuthenticationAuditRepository $authenticationAuditRepository,
    ) {}

    public static function getSubscribedEvents(): array {
        return [KernelEvents::REQUEST => ["onRequest", 0]];
    }

    public function onRequest(RequestEvent $event): void {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get("_route") === "app_login") {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof IdentityUser || !$request->hasSession()) {
            return;
        }

        if ($this->identitySessionRepository->isCurrent($user->getId(), $request->getSession()->getId(), new \DateTimeImmutable())) {
            return;
        }

        $this->authenticationAuditRepository->record("session_invalidated", $user->getId(), $user->getUserIdentifier(), $request);
        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate("app_login")));
    }
}
