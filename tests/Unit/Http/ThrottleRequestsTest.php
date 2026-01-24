<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use VelvetCMS\Contracts\CacheDriver;
use VelvetCMS\Http\Middleware\ThrottleRequests;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;
use VelvetCMS\Tests\Support\TestCase;

final class ThrottleRequestsTest extends TestCase
{
    private ThrottleRequests $middleware;
    private CacheDriver $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock CacheDriver
        $this->cache = new class implements CacheDriver {
            public array $storage = [];
            
            public function get(string $key, mixed $default = null): mixed {
                return $this->storage[$key] ?? $default;
            }
            public function set(string $key, mixed $value, int $ttl = 3600): bool {
                $this->storage[$key] = $value;
                return true;
            }
            public function has(string $key): bool { return isset($this->storage[$key]); }
            public function delete(string $key): bool { unset($this->storage[$key]); return true; }
            public function clear(): bool { $this->storage = []; return true; }
            public function remember(string $key, int $ttl, callable $callback): mixed { return $callback(); }
        };

        $this->middleware = new ThrottleRequests($this->cache);
        
        // Set config for testing
        config([
            'http.rate_limit.max_attempts' => 2,
            'http.rate_limit.decay_minutes' => 1,
        ]);
    }

    public function test_allows_requests_under_limit(): void
    {
        $request = Request::capture();
        $next = fn() => Response::html('ok');

        // 1st request
        $response = $this->middleware->handle($request, $next);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('2', $response->getHeader('X-RateLimit-Limit'));
        $this->assertSame('1', $response->getHeader('X-RateLimit-Remaining'));

        // 2nd request
        $response = $this->middleware->handle($request, $next);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('0', $response->getHeader('X-RateLimit-Remaining'));
    }

    public function test_blocks_requests_over_limit(): void
    {
        $request = Request::capture();
        $next = fn() => Response::html('ok');

        // Consume limit
        $this->middleware->handle($request, $next);
        $this->middleware->handle($request, $next);

        // 3rd request - should be blocked
        $response = $this->middleware->handle($request, $next);
        
        $this->assertSame(429, $response->getStatus());
        $this->assertStringContainsString('Too Many Requests', $response->getContent());
        $this->assertNotNull($response->getHeader('Retry-After'));
    }
}
