<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Http;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;
use VelvetCMS\Http\Routing\Router;
use VelvetCMS\Tests\Support\TestCase;

final class RequestLifecycleTest extends TestCase
{
    private Application $app;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application($this->tmpDir);
        $events = new EventDispatcher();
        $this->app->instance('events', $events);
        $this->app->instance(EventDispatcher::class, $events);

        $this->router = new Router($events);
        $this->router->setApp($this->app);

        // Reset server variables
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    public function testBasicGetRequestReturnsResponse(): void
    {
        $this->router->get('/', function (Request $request) {
            return Response::html('<h1>Home</h1>');
        });

        $_SERVER['REQUEST_URI'] = '/';
        $request = Request::capture();

        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('<h1>Home</h1>', $response->getContent());
    }

    public function testRouteWithParameters(): void
    {
        $this->router->get('/user/{id}', function (Request $request, string $id) {
            return Response::json(['user_id' => $id]);
        });

        $_SERVER['REQUEST_URI'] = '/user/123';
        $request = Request::capture();

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('"user_id":"123"', $response->getContent());
    }

    public function testControllerActionWithAutowiring(): void
    {
        // Register a concrete test service
        $this->app->singleton(TestService::class, fn () => new TestService());

        $this->router->get('/test', [TestController::class, 'index']);

        $_SERVER['REQUEST_URI'] = '/test';
        $request = Request::capture();

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Service injected!', $response->getContent());
    }

    public function test404ResponseForUnmatchedRoute(): void
    {
        $this->router->get('/exists', function (Request $request) {
            return Response::html('Found');
        });

        $_SERVER['REQUEST_URI'] = '/does-not-exist';
        $request = Request::capture();

        $response = $this->router->dispatch($request);

        $this->assertSame(404, $response->getStatus());
    }

    public function testStringResponseConvertedToHtmlResponse(): void
    {
        $this->router->get('/string', function (Request $request) {
            return 'Plain string response';
        });

        $_SERVER['REQUEST_URI'] = '/string';
        $request = Request::capture();

        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Plain string response', $response->getContent());
    }

    public function testArrayResponseConvertedToJsonResponse(): void
    {
        $this->router->get('/array', function (Request $request) {
            return ['status' => 'ok', 'data' => [1, 2, 3]];
        });

        $_SERVER['REQUEST_URI'] = '/array';
        $request = Request::capture();

        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('"status":"ok"', $response->getContent());
    }
}

// Test service for autowiring
class TestService
{
    public function getMessage(): string
    {
        return 'Service injected!';
    }
}

// Test controller for autowiring
class TestController
{
    public function __construct(
        private TestService $service
    ) {
    }

    public function index(Request $request): Response
    {
        $message = $this->service->getMessage();
        return Response::html("<h1>{$message}</h1>");
    }
}
