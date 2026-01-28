<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Router;

use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Http\Response;
use VelvetCMS\Http\Routing\Router;
use VelvetCMS\Tests\Support\TestCase;

final class RouterDispatchTest extends TestCase
{
    public function test_head_falls_back_to_get_route(): void
    {
        $router = new Router(new EventDispatcher());
        $router->get('/ping', fn () => Response::html('pong'));

        $response = $router->dispatch($this->makeRequest('HEAD', '/ping'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('pong', $response->getContent());
    }

    public function test_method_not_allowed_includes_allow_header(): void
    {
        $router = new Router(new EventDispatcher());
        $router->post('/submit', fn () => Response::html('ok'));

        $response = $router->dispatch($this->makeRequest('GET', '/submit'));

        $this->assertSame(405, $response->getStatus());
        $this->assertSame('POST', $response->getHeaders()['Allow'] ?? '');
    }

    public function test_global_middleware_runs_before_route_middleware(): void
    {
        $router = new Router(new EventDispatcher());

        $log = [];
        $router->registerMiddleware('a', function ($request, $next) use (&$log) {
            $log[] = 'a';
            return $next($request);
        });
        $router->registerMiddleware('b', function ($request, $next) use (&$log) {
            $log[] = 'b';
            return $next($request);
        });

        $router->pushMiddleware('a');
        $router->get('/path', fn () => Response::html('ok'))->middleware('b');

        $response = $router->dispatch($this->makeRequest('GET', '/path'));

        $this->assertSame(['a', 'b'], $log);
        $this->assertSame('ok', $response->getContent());
    }
}
