<?php

declare(strict_types=1);

/**
 * Shared application bootstrap for HTTP, CLI, and tests.
 */

use VelvetCMS\Content\Index\JsonPageIndex;
use VelvetCMS\Content\Index\PageIndex;
use VelvetCMS\Content\Index\PageIndexer;
use VelvetCMS\Content\Index\SqlitePageIndex;
use VelvetCMS\Contracts\CacheDriver;
use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Contracts\DataStore;
use VelvetCMS\Core\Application;
use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Core\Paths;
use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Content\FileDriver;
use VelvetCMS\Drivers\Data\AutoDataStore;
use VelvetCMS\Services\ContentParser;
use VelvetCMS\Services\PageService;

$basePath = Paths::fromBootstrapEnvironment()->base();

if (!defined('VELVET_BASE_PATH')) {
    define('VELVET_BASE_PATH', $basePath);
}

$app = new Application($basePath);

// Generic data store for modules (auto-switches between file and database)
$app->singleton('data', function () use ($app) {
    // Try to get database connection, but don't fail if unavailable
    $connection = null;
    try {
        $connection = $app->make(Connection::class);
    } catch (\Throwable) {
        // Database not configured or unavailable
    }

    return new AutoDataStore($connection, storage_path('data'));
});
$app->alias('data', DataStore::class);
$app->alias('data', AutoDataStore::class);

$app->singleton(PageIndexer::class, function () {
    return new PageIndexer();
});

$app->singleton(PageIndex::class, function () use ($app) {
    $driver = (string) config('content.drivers.file.index.driver', 'json');

    return match ($driver) {
        'sqlite' => new SqlitePageIndex(
            $app->make(Connection::class),
        ),
        default => new JsonPageIndex(
            (string) config('content.drivers.file.index.json.path', storage_path('index/page-index.json')),
        ),
    };
});

// File-native page content
$app->singleton('content.driver', function () use ($app) {
    return new FileDriver(
        $app->make(ContentParser::class),
        $app->make(PageIndex::class),
        $app->make(PageIndexer::class),
        config('content.drivers.file.path', content_path('pages')),
    );
});
$app->alias('content.driver', ContentDriver::class);

// Page service orchestrator
$app->singleton('pages', function () use ($app) {
    return new PageService(
        $app->make(ContentDriver::class),
        $app->make(PageIndex::class),
        $app->make(EventDispatcher::class),
        $app->make(CacheDriver::class),
        $app->make(\VelvetCMS\Support\Cache\CacheTagManager::class),
        $app->make(\VelvetCMS\Core\ConfigRepository::class)
    );
});
$app->alias('pages', PageService::class);

return $app;
