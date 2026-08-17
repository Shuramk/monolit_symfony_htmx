<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Identity\ReadModel\MenuView;
use App\Domain\Identity\Security\IdentityUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NavigationExtension extends AbstractExtension {
    public function __construct(private readonly TokenStorageInterface $tokenStorage, private readonly MenuView $menuView) {
    }

    public function getFunctions(): array {
        return [new TwigFunction("app_menu", $this->menu(...))];
    }

    /** @return list<\App\Domain\Identity\ReadModel\Dtos\MenuItemDto> */
    public function menu(): array {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof IdentityUser) {
            return [];
        }

        return $this->menuView->getForUserId($user->getId());
    }
}
