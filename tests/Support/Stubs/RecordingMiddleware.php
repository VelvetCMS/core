<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Stubs;

use VelvetCMS\Http\Middleware\MiddlewareInterface;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

class RecordingMiddleware implements MiddlewareInterface
{
    public static array $calls = [];

    public function handle(Request $request, callable $next): Response
    {
        self::$calls[] = 'before';
        $response = $next($request);
        self::$calls[] = 'after';

        return $response;
    }
}
