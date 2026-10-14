<?php declare(strict_types=1);

namespace App\Application\Controller;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repository\IdentityMenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController {
    #[Route("/login", name: "app_login", methods: ["GET", "POST"])]
    public function login(AuthenticationUtils $authenticationUtils, IdentityMenuRepository $menu, #[Autowire('%env(LEGACY_ADMIN_URL)%')] string $legacyAdminUrl): Response {
        $user = $this->getUser();
        if ($user instanceof User) {
            $startPage = $user->getStartPageId();
            $url = $startPage === null ? null : $menu->startPageUrl($user->getId(), $startPage, $legacyAdminUrl);

            return $this->redirect($url ?? $this->generateUrl('enterprise_profile'));
        }

        return $this->render("security/login.html.twig", [
            "lastUsername" => $authenticationUtils->getLastUsername(),
            "error" => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route("/logout", name: "app_logout", methods: ["POST"])]
    public function logout(): never {
        throw new \LogicException("This route is intercepted by Symfony Security.");
    }
}
