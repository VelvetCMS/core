<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Middleware;

use VelvetCMS\Contracts\MiddlewareInterface;
use VelvetCMS\Http\RateLimiting\Limit;
use VelvetCMS\Http\RateLimiting\RateLimiter;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

class ThrottleRequests implements MiddlewareInterface
{
    private ?string $limiterName = null;

    public function __construct(
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function setLimiter(string $limiter): self
    {
        $this->limiterName = $limiter;
        return $this;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!config('http.rate_limit.enabled', true)) {
            return $next($request);
        }

        $ip = $request->ip() ?? 'unknown';
        if ($this->rateLimiter->isWhitelisted($ip)) {
            return $next($request);
        }

        $limit = $this->resolveLimit($request);

        if ($limit->isUnlimited()) {
            return $next($request);
        }

        $key = $this->rateLimiter->resolveKey($request, $limit, $this->limiterName);
        $result = $this->rateLimiter->attempt($key, $limit->maxAttempts, $limit->decaySeconds);

        if (!$result['allowed']) {
            return $this->buildTooManyRequestsResponse($limit->maxAttempts, $result['retryAfter']);
        }

        $response = $next($request);
        return $this->addHeaders($response, $limit->maxAttempts, $result['remaining'], $result['retryAfter']);
    }

    protected function resolveLimit(Request $request): Limit
    {
        if ($this->limiterName === null) {
            return $this->getDefaultLimit();
        }

        // Inline format: 'attempts,minutes'
        if (str_contains($this->limiterName, ',')) {
            return $this->parseInlineLimit($this->limiterName);
        }

        // Try named limiter from service
        $limit = $this->rateLimiter->limiter($this->limiterName, $request);
        if ($limit !== null) {
            return $limit;
        }

        // Try config limiter
        $configuredLimit = $this->getConfiguredLimit($this->limiterName);
        if ($configuredLimit !== null) {
            return $configuredLimit;
        }

        return $this->getDefaultLimit();
    }

    protected function getDefaultLimit(): Limit
    {
        $defaultName = config('http.rate_limit.default', 'standard');

        return $this->getConfiguredLimit($defaultName) ?? new Limit();
    }

    protected function getConfiguredLimit(string $name): ?Limit
    {
        $limiters = config('http.rate_limit.limiters', []);

        if (!isset($limiters[$name])) {
            return null;
        }

        $config = $limiters[$name];

        return new Limit(
            maxAttempts: (int) ($config['attempts'] ?? 60),
            decaySeconds: (int) ($config['decay'] ?? 60),
            by: $config['by'] ?? 'ip'
        );
    }

    protected function parseInlineLimit(string $definition): Limit
    {
        $parts = explode(',', $definition);

        $attempts = (int) ($parts[0] ?? 60);
        $minutes = (int) ($parts[1] ?? 1);

        return new Limit($attempts, $minutes * 60);
    }

    protected function buildTooManyRequestsResponse(int $limit, int $retryAfter): Response
    {
        return Response::json([
            'error' => 'Too Many Requests',
            'message' => 'You have exceeded the rate limit.',
        ], 429)
            ->header('Retry-After', (string) $retryAfter)
            ->header('X-RateLimit-Limit', (string) $limit)
            ->header('X-RateLimit-Remaining', '0')
            ->header('X-RateLimit-Reset', (string) (time() + $retryAfter));
    }

    private function addHeaders(Response $response, int $limit, int $remaining, int $reset): Response
    {
        return $response
            ->header('X-RateLimit-Limit', (string) $limit)
            ->header('X-RateLimit-Remaining', (string) max(0, $remaining))
            ->header('X-RateLimit-Reset', (string) (time() + $reset));
    }
}
