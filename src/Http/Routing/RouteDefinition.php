<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Routing;

/**
 * Fluent builder for additional route metadata
 */
class RouteDefinition
{
    public function __construct(
        private readonly Router $router,
        private readonly int $routeId
    ) {
    }

    /**
     * Attach middleware to this route.
     */
    public function middleware(string|array|callable $middleware): self
    {
        $middlewareList = is_array($middleware) ? $middleware : [$middleware];
        $this->router->attachRouteMiddleware($this->routeId, $middlewareList);

        return $this;
    }

    /**
     * Internal helper for backward compatibility when chaining directly on the definition.
     */
    public function getRouteId(): int
    {
        return $this->routeId;
    }
}
