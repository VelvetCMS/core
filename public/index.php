<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use VelvetCMS\Exceptions\ExceptionHandlerInterface;
use VelvetCMS\Exceptions\NotFoundException;
use VelvetCMS\Http\AssetServer;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

$request = Request::capture();
\VelvetCMS\Core\Tenancy\TenancyManager::bootstrapFromRequest($request);

$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

$response = AssetServer::serve($request);
if ($response !== null) {
    $response->send();
    exit;
}

$router = $app->make('router');

$routeCacheFile = storage_path('cache/routes.php');
if (file_exists($routeCacheFile)) {
    $cachedRoutes = require $routeCacheFile;
    if (is_array($cachedRoutes)) {
        $router->loadCachedRoutes($cachedRoutes);
    }
} else {
    $app->registerDefaultRoutes($router);
}

$handler = $app->make(ExceptionHandlerInterface::class);
try {
    $response = $router->dispatch($request);
} catch (\Throwable $e) {
    $handler->report($e, $request);
    $response = $handler->render($e, $request);

    if (config('app.debug', false)) {
        throw $e;
    }
}

$response->send();