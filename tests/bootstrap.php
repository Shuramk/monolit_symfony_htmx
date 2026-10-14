<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$testDatabaseUrlOverride = getenv('APP_DATABASE_URL');

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

foreach ([$testDatabaseUrlOverride, $_SERVER['APP_DATABASE_URL'] ?? ''] as $testDatabaseUrl) {
    if ($testDatabaseUrl === false) continue;
    if (parse_url($testDatabaseUrl, PHP_URL_PATH) !== '/monolit_test') {
        throw new RuntimeException('Tests may connect only to monolit_test. Check APP_DATABASE_URL in the test environment.');
    }
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
