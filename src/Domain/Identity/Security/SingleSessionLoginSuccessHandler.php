<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Repository\IdentitySessionRepository;
use App\Domain\Identity\Audit\AuthenticationAuditRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class SingleSessionLoginSuccessHandler implements AuthenticationSuccessHandlerInterface {
    public function __construct(private IdentitySessionRepository $identitySessionRepository, private UrlGeneratorInterface $urlGenerator, private AuthenticationAuditRepository $authenticationAuditRepository) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response {
        $user = $token->getUser();

        if (!$user instanceof IdentityUser) {
            throw new \LogicException("Expected IdentityUser after login.");
        }

        $request->getSession()->migrate(true);
        $this->identitySessionRepository->replace($user->getId(), $request->getSession()->getId(), new \DateTimeImmutable());
        $this->authenticationAuditRepository->record("login_success", $user->getId(), $user->getUserIdentifier(), $request);

        return new RedirectResponse($this->urlGenerator->generate("enterprise_profile"));
    }
}
