<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity\Repository;

use App\Domain\Identity\ReadModel\MenuView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IdentityMenuRepositoryTest extends KernelTestCase {
    public function testItBuildsTheLegacyNavigationForAnImportedUser(): void {
        self::bootKernel();

        $menu = self::getContainer()->get(MenuView::class)->getForUserId(1);

        self::assertNotEmpty($menu);
        $urls = [];
        foreach ($menu as $item) {
            $urls[] = $item->url;
            foreach ($item->children as $child) {
                $urls[] = $child->url;
            }
        }

        self::assertContains("/enterprise", $urls);
    }
}
