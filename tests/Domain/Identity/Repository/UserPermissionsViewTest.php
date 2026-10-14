<?php declare(strict_types=1);

namespace App\Tests\Domain\Identity\Repository;

use App\Domain\Identity\ReadModel\UserPermissionsView;
use App\Domain\Identity\Repository\UserSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserPermissionsViewTest extends KernelTestCase {
    public function testAllNumericPermissionsArePreservedOnceInTheTree(): void {
        self::bootKernel(); $container = self::getContainer();
        $tree = $container->get(UserPermissionsView::class)->tree(); $flat = [];
        $visit = static function (array $nodes) use (&$visit,&$flat): void { foreach ($nodes as $node) { $flat[$node['id']] = $node; $visit($node['children']); } };
        $visit($tree);
        $ids = array_map('intval',array_column($container->get(UserSettingsRepository::class)->permissions(),'legacy_division_id'));
        self::assertEqualsCanonicalizing($ids,array_keys($flat));
        self::assertSame(200,$flat[220]['parent']); self::assertSame(220,$flat[227]['parent']);
        self::assertSame(100,$flat[101]['parent']); self::assertFalse($flat[227]['enabled']);
    }
}
