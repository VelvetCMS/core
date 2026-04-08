<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\ConfigRepository;
use VelvetCMS\Core\Paths;
use VelvetCMS\Core\Tenancy\TenancyState;
use VelvetCMS\Http\Middleware\StartSessionMiddleware;
use VelvetCMS\Http\Response;
use VelvetCMS\Tests\Support\TestCase;

final class StartSessionMiddlewareTest extends TestCase
{
    public function test_returns_response_and_calls_next(): void
    {
        $app = Application::getInstance();
        $middleware = new StartSessionMiddleware(
            $app->make(ConfigRepository::class),
            $app->make(Paths::class),
            $app->make(TenancyState::class),
        );
        $request = $this->makeRequest('GET', '/');

        $response = $middleware->handle($request, fn () => Response::html('ok'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('ok', $response->getContent());
    }
}
