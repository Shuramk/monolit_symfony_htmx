<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\ShipmentQuantityParser;
use PHPUnit\Framework\TestCase;

final class ShipmentQuantityParserTest extends TestCase
{
    public function testItParsesOnlyPrimitiveLegacyShipmentMaps(): void
    {
        $quantities = (new ShipmentQuantityParser())->parse("a:3:{s:3:\"822\";s:4:\"10.8\";i:1545;d:-1;s:2:\"17\";i:2;}");

        self::assertSame([822 => 10800, 1545 => -1000, 17 => 2000], $quantities);
    }

    public function testItRejectsUnexpectedSerializedPayload(): void
    {
        self::assertSame([], (new ShipmentQuantityParser())->parse("O:8:\"stdClass\":0:{}"));
    }
}
