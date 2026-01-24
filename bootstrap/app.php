<?php

declare(strict_types=1);

/**
 * Shared application bootstrap for HTTP, CLI, and tests.
 */

use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Contracts\DataStore;
use VelvetCMS\Core\Application;
use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Http\Routing\Router;
use VelvetCMS\Database\Connection;
use VelvetCMS\Contracts\CacheDriver;
use VelvetCMS\Drivers\Content\AutoDriver;
use VelvetCMS\Drivers\Content\DBDriver;
use VelvetCMS\Drivers\Content\FileDriver;
use VelvetCMS\Drivers\Content\HybridDriver;
use VelvetCMS\Drivers\Data\AutoDataStore;
use VelvetCMS\Services\ContentParser;
use VelvetCMS\Services\PageService;

if (!defined('VELVET_BASE_PATH')) {
    define('VELVET_BASE_PATH', dirname(__DIR__));
}

$app = new Application(VELVET_BASE_PATH);

// Set global application instance for helpers
Application::setInstance($app);

// Generic data store for modules (auto-switches between file and database)
$app->singleton('data', function() use ($app) {
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

// Content driver selection
$app->singleton('content.driver', function() use ($app) {
    $driver = config('content.driver', 'file');
    $parser = $app->make(ContentParser::class);
    $contentPath = config('content.drivers.file.path', content_path('pages'));
    
    // Get database connection (lazy - only used by db/hybrid/auto drivers)
    $getDb = static fn(): Connection => $app->make(Connection::class);

    return match ($driver) {
        'file' => new FileDriver($parser, $contentPath),
        'db' => new DBDriver($getDb()),
        'hybrid' => new HybridDriver($parser, $getDb(), $contentPath),
        'auto' => new AutoDriver(
            new FileDriver($parser, $contentPath),
            new HybridDriver($parser, $getDb(), $contentPath),
            $getDb(),
            (int) config('content.drivers.auto.threshold', 100)
        ),
        default => new FileDriver($parser, $contentPath),
    };
});
$app->alias('content.driver', ContentDriver::class);

// Page service orchestrator
$app->singleton('pages', function() use ($app) {
    return new PageService(
        $app->make(ContentDriver::class),
        $app->make(EventDispatcher::class),
        $app->make(CacheDriver::class),
        $app->make(\VelvetCMS\Support\Cache\CacheTagManager::class)
    );
});
$app->alias('pages', PageService::class);

// Module system - then load and boot modules
$app->singleton('modules', function() use ($app) {
    return new \VelvetCMS\Core\ModuleManager($app);
});
$app->alias('modules', \VelvetCMS\Core\ModuleManager::class);

$moduleManager = $app->make('modules');
$moduleManager->load()->register()->boot();

return $app;
