<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set test environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['CACHE_DRIVER'] = 'file';
$_ENV['CONTENT_DRIVER'] = 'file';

// Define base path for tests
define('VELVET_BASE_PATH', dirname(__DIR__));

// Create test storage directories if they don't exist
$testDirs = [
    __DIR__ . '/fixtures/content/pages',
    __DIR__ . '/fixtures/storage/cache',
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
