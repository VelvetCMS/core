<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use VelvetCMS\Http\Middleware\StartSessionMiddleware;
use VelvetCMS\Http\Response;
use VelvetCMS\Tests\Support\TestCase;

final class StartSessionMiddlewareTest extends TestCase
{
    public function test_returns_response_and_calls_next(): void
    {
        $middleware = new StartSessionMiddleware();
        $request = $this->makeRequest('GET', '/');

        $response = $middleware->handle($request, fn() => Response::html('ok'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('ok', $response->getContent());
    }
}
