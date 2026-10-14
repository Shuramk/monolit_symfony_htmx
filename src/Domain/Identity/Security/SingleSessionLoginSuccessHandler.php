<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repository\IdentityMenuRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class SingleSessionLoginSuccessHandler implements AuthenticationSuccessHandlerInterface {
    public function __construct(private UrlGeneratorInterface $urlGenerator, private IdentityMenuRepository $menu, #[Autowire('%env(LEGACY_ADMIN_URL)%')] private string $legacyAdminUrl) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response {
        $user = $token->getUser();

        if (!$user instanceof User) {
            throw new \LogicException("Expected User after login.");
        }

        $startPage = $user->getStartPageId();
        $url = $startPage === null ? null : $this->menu->startPageUrl($user->getId(),$startPage,$this->legacyAdminUrl);
        return new RedirectResponse($url ?? $this->urlGenerator->generate("enterprise_profile"));
    }
}
