<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Exceptions;

use Psr\Log\NullLogger;
use VelvetCMS\Exceptions\Handler;
use VelvetCMS\Exceptions\HttpException;
use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;
use VelvetCMS\Tests\Support\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    public function test_http_exception_renders_json_when_requested(): void
    {
        $handler = new Handler(new EventDispatcher(), new NullLogger());

        $request = $this->makeRequest('GET', '/api', [], ['Accept' => 'application/json']);
        $response = $handler->render(new HttpException(404, 'Not here'), $request);

        $this->assertSame(404, $response->getStatus());
        $this->assertStringContainsString('Not here', $response->getContent());
    }

    public function test_generic_exception_in_debug_returns_html_trace(): void
    {
        config(['app.debug' => true]);
        $handler = new Handler(new EventDispatcher(), new NullLogger());

        $request = $this->makeRequest('GET', '/page');
        $response = $handler->render(new \RuntimeException('boom'), $request);

        $this->assertSame(500, $response->getStatus());
        $this->assertStringContainsString('RuntimeException', $response->getContent());
    }
}
