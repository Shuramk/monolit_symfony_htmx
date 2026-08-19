<?php declare(strict_types=1);

namespace App\Tests\Domain\Enterprise\Repository;

use App\Domain\Enterprise\Repository\EnterpriseViewRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class EnterpriseDocumentProfileTest extends TestCase {
    public function testMissingProfileDoesNotQueryPhones(): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->willReturn(false);
        $connection->expects(self::never())->method('fetchFirstColumn');
        $repository = new EnterpriseViewRepository($connection, $this->createStub(SerializerInterface::class));

        self::assertNull($repository->findDocumentProfile());
    }

    public function testPhonesAreLoadedForTheSelectedProfileWithNamedParameters(): void {
        $connection = $this->createMock(Connection::class);
        $profile = ['id' => 7, 'legal_name_vat' => 'ТОВ Монолит+', 'payment_account_vat' => 'UA000'];
        $connection->expects(self::once())->method('fetchAssociative')->willReturn($profile);
        $connection->expects(self::once())->method('fetchFirstColumn')->with(
            self::callback(static fn (string $sql): bool => str_contains($sql, ':profileId') && str_contains($sql, 'ORDER BY position, id')),
            ['profileId' => 7],
        )->willReturn(['0975555898', '0954852424']);
        $repository = new EnterpriseViewRepository($connection, $this->createStub(SerializerInterface::class));

        self::assertSame($profile + ['phones' => ['0975555898', '0954852424']], $repository->findDocumentProfile());
    }
}
