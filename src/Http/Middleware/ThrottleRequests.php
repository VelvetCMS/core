<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Middleware;

use VelvetCMS\Contracts\CacheDriver;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

class ThrottleRequests implements MiddlewareInterface
{
    public function __construct(
        private readonly CacheDriver $cache
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        // Get configuration or defaults
        $maxAttempts = (int) config('http.rate_limit.max_attempts', 60);
        $decayMinutes = (int) config('http.rate_limit.decay_minutes', 1);
        $decaySeconds = $decayMinutes * 60;

        // Generate cache key based on IP
        $ip = $request->ip() ?? 'unknown';
        $key = 'throttle:' . $ip;
        
        $data = $this->cache->get($key);

        // First hit
        if ($data === null) {
            $this->cache->set($key, [
                'hits' => 1,
                'reset_at' => time() + $decaySeconds
            ], $decaySeconds);
            
            $remaining = $maxAttempts - 1;
            $response = $next($request);
            return $this->addHeaders($response, $maxAttempts, $remaining, $decaySeconds);
        }

        // Ensure data is valid
        if (!is_array($data) || !isset($data['hits'], $data['reset_at'])) {
             $this->cache->delete($key);
             return $next($request);
        }

        // Check limit
        if ($data['hits'] >= $maxAttempts) {
            $retryAfter = $data['reset_at'] - time();
            
            return Response::json([
                'error' => 'Too Many Requests',
                'message' => 'You have exceeded the rate limit.'
            ], 429)->header('Retry-After', (string) max(0, $retryAfter));
        }

        // Increment hits
        $data['hits']++;
        $ttl = max(1, $data['reset_at'] - time());
        $this->cache->set($key, $data, $ttl);

        $remaining = $maxAttempts - $data['hits'];
        
        $response = $next($request);
        return $this->addHeaders($response, $maxAttempts, $remaining, $ttl);
    }

    private function addHeaders(Response $response, int $limit, int $remaining, int $reset): Response
    {
        return $response
            ->header('X-RateLimit-Limit', (string) $limit)
            ->header('X-RateLimit-Remaining', (string) max(0, $remaining))
            ->header('X-RateLimit-Reset', (string) (time() + $reset));
    }
}
