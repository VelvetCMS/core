<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Middleware;

use Throwable;
use VelvetCMS\Contracts\MiddlewareInterface;
use VelvetCMS\Exceptions\ExceptionHandlerInterface;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

final class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExceptionHandlerInterface $handler,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            $this->handler->report($e, $request);
            return $this->handler->render($e, $request);
        }
    }
}
