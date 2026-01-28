# Upgrade Guide

## 1.4.0

### Logging

FileLogger now supports daily rotation and automatic cleanup.

**New config** (`config/logging.php`):
```php
'logging' => [
    'path' => storage_path('logs/velvet.log'),
    'level' => 'info',
    'daily' => true,       // Creates velvet-2026-01-28.log
    'max_files' => 14,     // Keep 2 weeks
],
```

Old `app.log_level` config still works as fallback.

### Validation

Custom validation rules via `Validator::extend()`.

```php
// Register a custom rule
Validator::extend('slug', function($value, $parameter, $data, $field) {
    if (!preg_match('/^[a-z0-9-]+$/', $value)) {
        return "The {$field} must be a valid slug.";
    }
    return true;
});

// Use it
Validator::make($data, ['url' => 'required|slug'])->validate();
```

Return `true` if valid, `false` for generic error, or a string for custom message.

---

## 1.3.0

### Rate Limiting

Rewritten rate limiting system with named limiters, dynamic limits, and module extensibility.

**Breaking:**
- `ThrottleRequests` now requires `RateLimiter` instead of `CacheDriver` (container handles this automatically)
- Config keys `max_attempts`/`decay_minutes` removed — use `limiters` array instead

**New config structure:**
```php
'rate_limit' => [
    'enabled' => true,
    'default' => 'standard',
    'limiters' => [
        'standard' => ['attempts' => 60, 'decay' => 60, 'by' => 'ip'],
        'api' => ['attempts' => 120, 'decay' => 60, 'by' => 'ip'],
        'auth' => ['attempts' => 5, 'decay' => 60, 'by' => 'ip'],
        'strict' => ['attempts' => 10, 'decay' => 60, 'by' => 'ip'],
    ],
    'whitelist' => ['127.0.0.1', '::1'],
],
```

**New:**
- Named limiters (`standard`, `api`, `auth`, `strict`) or custom
- Dynamic limits via closures for user-aware throttling
- IP whitelist bypass
- Flexible keys: `ip`, `user`, `ip_route`, or custom
- `Limit` value object: `Limit::perMinute(60)`, `Limit::perHour(100)`, `Limit::none()`
- Modules register limiters via `RateLimiter::for()`
