<?php declare(strict_types=1);

namespace App\Application\Middleware\Attributes;

use Attribute;

#[Attribute]
class Formatter {
    public function __construct(
        public string $method,
        public array $params = [],
    ) {}
}