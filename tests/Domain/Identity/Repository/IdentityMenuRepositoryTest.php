<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity\Repository;

use App\Domain\Identity\ReadModel\MenuView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IdentityMenuRepositoryTest extends KernelTestCase {
    public function testSettingsTopTabOpensTheSymfonyProductSettings(): void {
        self::bootKernel();
        $menu = self::getContainer()->get(MenuView::class)->getForUserId(1);
        $settings = array_values(array_filter($menu, static fn ($item): bool => $item->divisionId === 1000));

        self::assertCount(1, $settings);
        self::assertSame('/settings/products', $settings[0]->url);
    }

    public function testOrdersTopTabOpensTheSymfonyOrderList(): void {
        self::bootKernel();
        $menu = self::getContainer()->get(MenuView::class)->getForUserId(1);
        $orders = array_values(array_filter($menu, static fn ($item): bool => $item->divisionId === 200));

        self::assertCount(1, $orders);
        self::assertSame('/orders', $orders[0]->url);
    }

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
        self::assertContains('/settings/products', $urls);
        self::assertContains('/warehouse/products', $urls);
        foreach ($urls as $url) {
            self::assertStringStartsWith('/', $url);
            self::assertStringStartsNotWith('//', $url);
        }
    }
}
