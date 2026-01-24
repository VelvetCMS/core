<?php

declare(strict_types=1);

namespace VelvetCMS\Exceptions;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

class Handler implements ExceptionHandlerInterface
{
    private LoggerInterface $logger;

    /** @var array<class-string, callable> */
    private array $renderers = [];

    /** @var array<class-string, callable> */
    private array $reporters = [];

    public function __construct(
        private readonly EventDispatcher $events,
        ?LoggerInterface $logger = null,
        array $renderers = [],
        array $reporters = []
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->renderers = $renderers;
        $this->reporters = $reporters;
    }

    public function report(Throwable $e, Request $request): void
    {
        $payload = ['exception' => $e, 'request' => $request];
        $this->events->dispatch('exception.reporting', $payload);

        if ($e instanceof ReportableExceptionInterface) {
            $e->report($this->logger);
            return;
        }

        foreach ($this->reporters as $class => $reporter) {
            if ($e instanceof $class) {
                $reporter($e, $request, $this->logger);
                return;
            }
        }

        $this->logger->error($e->getMessage(), ['exception' => $e]);
    }

    public function render(Throwable $e, Request $request): Response
    {
        $payload = ['exception' => $e, 'request' => $request];
        $this->events->dispatch('exception.rendering', $payload);

        if ($e instanceof RenderableExceptionInterface) {
            return $e->toResponse($request);
        }

        foreach ($this->renderers as $class => $renderer) {
            if ($e instanceof $class) {
                return $renderer($e, $request);
            }
        }

        if ($e instanceof HttpException) {
            return $e->toResponse($request);
        }

        return $this->toGenericResponse($e, $request);
    }

    private function toGenericResponse(Throwable $e, Request $request): Response
    {
        if (config('app.debug', false)) {
            $debugPayload = [
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => explode(PHP_EOL, $e->getTraceAsString()),
            ];

            return $request->expectsJson()
                ? Response::json($debugPayload, 500)
                : Response::html($this->renderDebugHtml($debugPayload), 500);
        }

        $message = 'Server Error';
        return $request->expectsJson()
            ? Response::json(['message' => $message, 'status' => 500], 500)
            : Response::error($message, 500);
    }

    private function renderDebugHtml(array $payload): string
    {
        $message = htmlspecialchars($payload['message'], ENT_QUOTES, 'UTF-8');
        $exception = htmlspecialchars($payload['exception'], ENT_QUOTES, 'UTF-8');
        $trace = implode('<br>', array_map(static fn(string $line): string => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'), $payload['trace']));

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Exception Debug</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #1e1e1e; color: #f5f5f5; }
        h1 { margin-top: 0; }
        .trace { margin-top: 1.5rem; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{$exception}</h1>
    <p>{$message}</p>
    <div class="trace">{$trace}</div>
</body>
</html>
HTML;
    }

    public function addRenderer(string $class, callable $renderer): void
    {
        $this->renderers[$class] = $renderer;
    }

    public function addReporter(string $class, callable $reporter): void
    {
        $this->reporters[$class] = $reporter;
    }
}
