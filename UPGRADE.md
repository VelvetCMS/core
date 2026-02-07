# Upgrade Guide

This document covers **breaking changes** and required actions when upgrading between versions. For new features and improvements, see the [release notes](https://github.com/VelvetCMS/core/releases).

## 1.7.0

### MiddlewareInterface namespace change

`MiddlewareInterface` moved from `VelvetCMS\Http\Middleware` to `VelvetCMS\Contracts`. Update your imports:

```php
// Before
use VelvetCMS\Http\Middleware\MiddlewareInterface;

// After
use VelvetCMS\Contracts\MiddlewareInterface;
```

### Module discovery path change

The module scan pattern in `config/modules.php` changed from `VelvetCMS-*` to `VelvetCMS*`. If you override the `paths` config, update your pattern accordingly.

---

## 1.5.0

### AutoDriver behavior change

AutoDriver no longer switches drivers at runtime. It evaluates once at boot and stays on that driver for the entire request lifecycle.

If you relied on automatic switching, manually migrate content when ready:

```bash
./velvet content:migrate hybrid
```

### Removed: `content:import`

Use `content:migrate db` instead:

```bash
./velvet content:migrate db --from=file
```

---

## 1.3.0

### Rate limiting rewrite

**Breaking:**
- `ThrottleRequests` now requires `RateLimiter` instead of `CacheDriver` (container handles this automatically)
- Config keys `max_attempts`/`decay_minutes` removed - use `limiters` array instead

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
