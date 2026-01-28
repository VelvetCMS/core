<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Router;

use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Http\Response;
use VelvetCMS\Http\Routing\Router;
use VelvetCMS\Tests\Support\TestCase;

final class RouterMethodOverrideTest extends TestCase
{
    public function test_post_with_method_spoofing_hits_put_route(): void
    {
        $router = new Router(new EventDispatcher());
        $router->put('/items/{id}', fn ($request, $id) => Response::html('updated:' . $id));

        $request = $this->makeRequest('POST', '/items/42', ['_method' => 'PUT']);
        $response = $router->dispatch($request);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('updated:42', $response->getContent());
    }
}
