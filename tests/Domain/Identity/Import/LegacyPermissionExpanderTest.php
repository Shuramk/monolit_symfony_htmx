<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity\Import;

use App\Domain\Identity\Import\LegacyPermissionExpander;
use PHPUnit\Framework\TestCase;

final class LegacyPermissionExpanderTest extends TestCase {
    public function testItParsesUniquePermissionsAndAddsLegacyConnectedPermissions(): void {
        $permissions = (new LegacyPermissionExpander())->expand("1030;501;1030;invalid;;");

        self::assertSame([1030, 1051, 1052, 1053, 501, 502, 503, 504, 507], $permissions);
    }
}
