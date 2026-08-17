<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enterprise\Repository;

use App\Domain\Enterprise\Repository\EnterpriseViewRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EnterpriseViewRepositoryTest extends KernelTestCase {
    public function testItReturnsTheImportedEnterpriseByTheLegacyId(): void {
        self::bootKernel();

        $enterprise = self::getContainer()->get(EnterpriseViewRepository::class)->getById(1);

        self::assertSame(1, $enterprise->id);
        self::assertNotEmpty($enterprise->displayName);
        self::assertIsArray($enterprise->phones);
    }
}
