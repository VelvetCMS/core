<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

use Closure;
use VelvetCMS\Http\RateLimiting\Limit;
use VelvetCMS\Http\Request;

interface RateLimiterInterface
{
    public function for(string $name, Limit|Closure $limiter): self;

    public function limiter(string $name, ?Request $request = null): ?Limit;

    public function hasLimiter(string $name): bool;

    /** @return array{allowed: bool, remaining: int, retryAfter: int} */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): array;

    public function attempts(string $key): int;

    public function remaining(string $key, int $maxAttempts): int;

    public function clear(string $key): bool;

    public function isWhitelisted(string $ip): bool;

    public function resolveKey(Request $request, Limit $limit, ?string $limiterName = null): string;
}
