# VelvetCMS Core

<p align="center">
  <img src="https://velvetcms.com/assets/images/logo-full-transparent.png" alt="VelvetCMS Logo" width="600"/>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-Apache%202.0-blue.svg" alt="License"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg" alt="PHP Version"></a>
</p>

---

## Why VelvetCMS Core?

Most PHP frameworks fall into two camps: heavyweight full-stack solutions with steep learning curves, or minimal routers that leave you rebuilding the same features for every project. VelvetCMS Core sits between those extremes.

It is built for developers and teams that want full-stack capabilities without framework opacity: explicit architecture, practical defaults, and a codebase you can reason about quickly.

That philosophy is what we call **Pragmatic Zero Magic**. You should be able to trace every part of your application's lifecycle without digging through layers of invisible behavior.

Core also deliberately tracks modern PHP aggressively. We target current stable releases early, remove legacy detours when they start warping the design, and prefer clear upgrade paths over dragging old compatibility baggage forward.

- **Explicit over Implicit**: Service bindings live in bootstrap code, routes and middleware are declared directly, and the request lifecycle stays readable end to end.
- **Pragmatic Convenience**: Autowiring helps with controllers and commands, but command registration, module loading, and core service wiring remain explicit where hidden resolution would cost clarity.
- **No Facades**: Dependencies arrive through constructors and method signatures, so tests and refactors work against real contracts instead of global static state.
- **Content First**: Page content is file-native, the parser understands frontmatter and block modes, and routing plus caching are tuned for publishing workloads rather than generic CRUD scaffolding.
- **Lean by Design**: The runtime stays small, the dependency graph stays short, and the codebase remains compact enough to inspect without framework archaeology.

---

## Features

### Core Architecture
- **Service Container**: Powerful dependency injection container. Explicit by default, with autowiring available as a pragmatic fallback.
- **Modular System**: Robust plugin architecture with PSR-4 autoloading, dependency resolution, and manifest-based loading for optimal performance.
- **Event Dispatcher**: Comprehensive event system allowing hooks into every part of the application lifecycle.
- **CLI Suite**: Extensive command-line tools for migrations, cache management, scaffolding, serving, diagnostics and more.

### Content & Data
- **File-Based Content**: Pages live as `.vlt` or `.md` files. They stay versionable, portable, and inspectable, with an on-disk index for fast lookups and no database requirement for page content.
- **Fluent Query Builder**: Expressive database abstraction layer supporting complex queries, joins, raw expressions, and automatic caching.
- **Schema Builder & Migrations**:
  - **Database Agnostic**: Write schemas once, run on SQLite, MySQL, or PostgreSQL.
  - **Fluent Interface**: Define tables and columns with an expressive syntax (`$table->string('title')->nullable()`).
  - **Migration System**: Version control for your database schema with `up` and `down` methods.
- **Pluggable Markdown Engine**:
  - **Drivers**: Support for `CommonMark` (recommended), `Parsedown`, or simple `HTML` pass-through.
  - **Extensible**: Custom template tags (`{{ }}`, `{!! !!}`) preserved in all drivers.
  - **CommonMark Features**: Tables, Strikethrough, Autolink, Task Lists (via extensions).
- **Velvet Content Blocks**: `.vlt` files with YAML frontmatter and block switches (`@markdown`, `@html`, `@text`, `@component`).
- **Data Store**: Simple key-value collections with `File`, `Database`, or `Auto` drivers for module data.
- **Smart Caching**: Multi-driver support (`Apcu`, `File`, `Redis`) for caching queries, pages, routes, configuration, templates, and markdown compilation.
- **File Storage**:
  - **Disk System**: Abstraction layer for file operations (write, read, stream).
  - **Drivers**: `Local` driver implemented (S3/Cloud ready interface).
  - **Security**: Built-in path normalization to prevent directory traversal.

### Web & Security
- **Robust Router**: Expressive routing engine with support for:
  - GET, POST, PUT, DELETE, PATCH methods
  - Named routes and parameters
  - Optional and wildcard parameters (`{param?}`, `{param*}`)
  - Middleware pipelines and global middleware
- **Validation Service**:
  - **Standalone Validator**: reuse validation logic anywhere (CLI, API, Imports).
  - **Rich Rule Set**: `required`, `email`, `url`, `min`, `max`, `regex`, `numeric`, `integer`, `boolean`, `alpha`, `alphanumeric`, `in`, `same`, `different`, `date`, `array`.
  - **Fluent API**: `Validator::make($data, $rules)->validate()`.
  - **Custom Rules**: `Validator::extend('slug', fn($value) => ...)` for project-specific validation.
- **View Engine**: Lightweight yet powerful templating system featuring:
  - Blade-like syntax (`{{ }}`, `{!! !!}`)
  - Blade-style comments (`{{-- ... --}}`)
  - Control structures (`@if`, `@foreach`)
  - Layout inheritance (`@extends`, `@section`, `@yield`)
  - Partials and includes (`@include`)
  - Namespace support for modular views
  - Configurable runtime string evaluation guard for `compileString()` / `safe()`
- **Asset Server**: Built-in static asset serving for development and production, handling MIME types and module assets efficiently.
- **Trusted Proxy Support**: Optional forwarded host/scheme/IP handling for reverse-proxy deployments (`http.trusted_proxies`).
- **Security Suite**: Built-in protection against common vectors.
  - ✅ **XSS Protection** - Auto-escaping template engine
  - ✅ **CSRF Protection** - Token-based validation
  - ✅ **SQL Injection** - Prepared statements by default, with explicit raw-expression escape hatches for advanced clauses
  - ✅ **Session Security** - httponly, secure, samesite flags
  - ✅ **Path Traversal** - Sanitization and realpath checks
- **Rate Limiting**:
  - **Named Limiters**: Pre-configured (`standard`, `api`, `auth`, `strict`) or custom.
  - **Module Support**: Modules register their own limiters via `RateLimiter` service.
  - **Dynamic Limits**: Callbacks for user-aware throttling (e.g., premium users).
  - **Flexible Keys**: Limit by IP, user, route, or custom keys.
  - **Whitelist**: Bypass for trusted IPs.
- **Exception Handling**:
  - **Custom Handler**: Centralized error handling and reporting.
  - **HTTP Exceptions**: Specialized exceptions for 404, 403, 500, etc.
  - **Renderable Exceptions**: Exceptions that can render their own response.
- **Logging**:
  - **PSR-3 Compliant**: Standard `LoggerInterface` with all log levels.
  - **Daily Rotation**: Optional dated log files (`velvet-2026-01-28.log`).
  - **Auto Cleanup**: Configurable `max_files` to prevent disk bloat.

### System & Scheduling
- **Task Scheduler**: Cron-style task scheduling defined in PHP.
  - **Fluent Frequency**: Define schedules naturally (`->daily()`, `->everyMinute()`).
  - **Command & Callback**: Schedule console commands or PHP closures.
- **Schedule Runner**: CLI `schedule:run` and optional WebCron endpoint (`/system/cron`).
- **Version Registry**: Centralized version management for core and modules.

### Multi-Tenancy
- **Single switch**: Enable/disable in [user/config/tenancy.php](user/config/tenancy.php) with `tenancy.enabled` (default off).
- **Resolvers**:
  - **Host**: Map hostnames or subdomains to tenants (`tenancy.host.map`, optional wildcard subdomains).
  - **Path**: Use a path segment as tenant id (`tenancy.path.segment`).
  - **Callback**: Provide a custom resolver that implements `TenantResolverInterface`.
- **Works with**:
  - Content and views (tenant-scoped roots under `user/tenants/<tenant>/...`)
  - Storage and modules (tenant-scoped roots/artifacts)
  - Cache/session isolation per tenant
  - Database-per-tenant setups when desired
- **Isolation**: Cache prefixing and tenant-scoped storage prevent cross-tenant collisions.
- **Sessions**: Path-based tenancy scopes cookies to the tenant path automatically.
- **CLI**: Set tenant context via `$TENANCY_TENANT` or `--tenant=<id>`; use `--all-tenants` for tenancy-aware orchestration commands.

---

## Requirements

- **PHP 8.4+** (+ extensions: `pdo`, `mbstring`, `json`)
- **Linux/WSL2**
- Composer
- SQLite, MySQL, or PostgreSQL (optional)

---

## Quick Start

```bash
# Clone and install
git clone https://github.com/VelvetCMS/core.git
cd core
composer install --no-dev

# Bootstrap
./velvet install

# Start dev server
./velvet serve
```

Visit `http://localhost:8000`

---

## Documentation

Full documentation available at **[velvetcms.com/docs](https://velvetcms.com/docs)**

Quick links:
- [Getting Started](https://velvetcms.com/docs/core/latest/guides/getting-started/overview)
- [Install Guide](https://velvetcms.com/docs/core/latest/guides/getting-started/installation)
- [Architecture Overview](https://velvetcms.com/docs/core/latest/guides/architecture/overview)

---

## License

VelvetCMS Core is licensed under the **Apache License 2.0**.

See [LICENSE](LICENSE) or [Our licensing page](https://velvetcms.com/license) for the full text.

---

## Contributing

Contributions welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

**Security Issues:** Email security@anvyr.dev (do not open public issues!)

---

## Credits

Built with ❤️ by [Anvyr](https://anvyr.dev).

See [composer.json](composer.json) for dependencies.