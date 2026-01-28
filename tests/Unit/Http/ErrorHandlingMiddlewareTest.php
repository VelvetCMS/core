<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use RuntimeException;
use VelvetCMS\Exceptions\ExceptionHandlerInterface;
use VelvetCMS\Http\Middleware\ErrorHandlingMiddleware;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;
use VelvetCMS\Tests\Support\TestCase;

final class ErrorHandlingMiddlewareTest extends TestCase
{
    public function test_passes_through_when_no_exception(): void
    {
        $handler = new RecordingExceptionHandler();
        $middleware = new ErrorHandlingMiddleware($handler);
        $request = $this->makeRequest('GET', '/');

        $response = $middleware->handle($request, fn (Request $req) => Response::html('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatus());
        $this->assertFalse($handler->reported);
        $this->assertFalse($handler->rendered);
    }

    public function test_reports_and_renders_on_exception(): void
    {
        $handler = new RecordingExceptionHandler();
        $middleware = new ErrorHandlingMiddleware($handler);
        $request = $this->makeRequest('GET', '/');

        $response = $middleware->handle($request, function () {
            throw new RuntimeException('Boom');
        });

        $this->assertTrue($handler->reported);
        $this->assertTrue($handler->rendered);
        $this->assertSame(500, $response->getStatus());
        $this->assertSame('handled', $response->getContent());
    }
}

final class RecordingExceptionHandler implements ExceptionHandlerInterface
{
    public bool $reported = false;
    public bool $rendered = false;

    public function report(\Throwable $e, Request $request): void
    {
        $this->reported = true;
    }

    public function render(\Throwable $e, Request $request): Response
    {
        $this->rendered = true;
        return Response::error('handled', 500);
    }
}
