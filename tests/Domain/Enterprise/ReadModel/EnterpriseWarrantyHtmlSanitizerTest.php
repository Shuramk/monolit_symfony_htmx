<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enterprise\ReadModel;

use App\Domain\Enterprise\ReadModel\EnterpriseWarrantyHtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class EnterpriseWarrantyHtmlSanitizerTest extends TestCase {
    public function testItRemovesScriptsAndEventHandlersFromLegacyHtml(): void {
        $html = (new EnterpriseWarrantyHtmlSanitizer())->sanitize(
            "<p>Гарантия</p><script>alert(1)</script><a href=\"javascript:alert(1)\" onclick=\"alert(1)\">ссылка</a>",
        );

        self::assertStringContainsString("<p>Гарантия</p>", $html);
        self::assertStringNotContainsString("<script", $html);
        self::assertStringNotContainsString("onclick", $html);
        self::assertStringNotContainsString("javascript:", $html);
    }
}
