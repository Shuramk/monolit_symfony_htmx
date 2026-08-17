<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ReadModel;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final readonly class EnterpriseWarrantyHtmlSanitizer {
    private HtmlSanitizer $sanitizer;

    public function __construct() {
        $this->sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowElement("table")
                ->allowElement("tbody")
                ->allowElement("tr")
                ->allowElement("td", ["colspan", "rowspan"])
                ->allowElement("th", ["colspan", "rowspan"])
                ->allowElement("br")
                ->allowLinkSchemes(["https", "http", "mailto"]),
        );
    }

    public function sanitize(string $sourceHtml): string {
        return $this->sanitizer->sanitize($sourceHtml);
    }
}
