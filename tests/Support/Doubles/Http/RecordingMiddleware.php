<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Doubles\Http;

use VelvetCMS\Contracts\MiddlewareInterface;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

final class RecordingMiddleware implements MiddlewareInterface
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
