# Upgrade Guide

This document covers **breaking changes** and required actions when upgrading between versions. For new features and improvements, see the [release notes](https://github.com/VelvetCMS/core/releases).

## 1.9.0

### Router pattern compilation hardening

Route static segments are now regex-escaped before parameter compilation. This fixes edge cases where literal regex characters (for example `.`) were treated as regex tokens.

**Impact:**
- Routes like `/api/v1.0/status` now match literally.
- If you intentionally relied on regex-like behavior in static route text, update those routes to use explicit parameters.

### QueryBuilder identifier validation

QueryBuilder now validates table/column/operator inputs for standard builder methods (`table`, `where`, joins, grouping, ordering, insert/update/upsert paths).

**Impact:**
- Unsafe identifier strings that previously slipped through now throw `InvalidArgumentException`.
- Use `RawExpression` / `raw()` for intentionally complex SQL fragments.

### Module lifecycle ownership cleanup

Module bootstrapping is now single-owned by core provider flow (duplicate bootstrap path removed).

**Impact:**
- Prevents duplicate module load/register/boot execution.
- No config changes required.

### Trusted proxy support (opt-in)

Request host/scheme/client IP can now use forwarded headers when the source proxy is trusted.

**New config (in `config/http.php`):**
```php
'trusted_proxies' => [
    'enabled' => false,
    'proxies' => [],
    'headers' => [
        'for' => 'X-Forwarded-For',
        'proto' => 'X-Forwarded-Proto',
        'host' => 'X-Forwarded-Host',
    ],
],
```

**Action required (only if behind reverse proxy):**
1. Enable `trusted_proxies.enabled`.
2. Set explicit proxy IPs/CIDRs in `trusted_proxies.proxies`.
3. Keep default header names unless your proxy uses custom ones.

### View string evaluation guard

`ViewEngine::compileString()` and `ViewEngine::safe()` are now controlled by configuration.

**New config (in `config/view.php`):**
```php
'allow_string_evaluation' => true,
```

**Recommendation:**
- For stricter production posture, set `allow_string_evaluation` to `false` unless you explicitly need runtime string templates.

### WebCron hardening options

WebCron authorization now uses constant-time token checks and supports optional defense-in-depth controls.

**New config (in `config/app.php`):**
```php
'cron_enabled' => false,
'cron_token' => '',
'cron_signed_urls' => false,
'cron_allowed_ips' => [],
'cron_rate_limit' => [
    'enabled' => false,
    'attempts' => 60,
    'decay' => 60,
],
```

**Action required (if using `/system/cron`):**
1. Ensure `cron_token` is set.
2. Optionally restrict `cron_allowed_ips`.
3. Optionally enable `cron_signed_urls` and/or `cron_rate_limit`.

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
