<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController {
    #[Route("/login", name: "app_login", methods: ["GET", "POST"])]
    public function login(AuthenticationUtils $authenticationUtils): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute("enterprise_profile");
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
