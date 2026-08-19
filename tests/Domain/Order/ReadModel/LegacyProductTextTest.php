<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Order\ReadModel\LegacyProductText;
use PHPUnit\Framework\TestCase;

final class LegacyProductTextTest extends TestCase
{
    public function testItConvertsLegacySuperscriptMarkupToPlainUnicode(): void
    {
        self::assertSame("Серый м²", (new LegacyProductText())->format("Серый м<sup>2</sup>"));
    }
}
