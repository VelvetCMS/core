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

Most PHP frameworks fall into two camps: heavyweight full-stack solutions with steep learning curves, or minimal routers that leave you rebuilding the same features for every project. VelvetCMS Core fills the gap.

We call it **Pragmatic Zero Magic**. You should be able to trace every part of your application's lifecycle without digging through layers of invisible behavior.

- **Explicit over Implicit**: Core services are wired manually. It's faster, clearer, and easier to debug.
- **Pragmatic Convenience**: Autowiring is available for your controllers and commands, but it's a tool, not a crutch.
- **No Facades**: No static proxies hiding dependencies. Standard injection and clear helpers.
- **Content First**: Drivers, caching, and routing are optimized for publishing, not generic "web apps".

---

## Features

### Core Architecture
- **Service Container**: Powerful dependency injection container. Explicit by default, with autowiring available as a pragmatic fallback.
- **Modular System**: Robust plugin architecture with PSR-4 autoloading, dependency resolution, and manifest-based loading for optimal performance.
- **Event Dispatcher**: Comprehensive event system allowing hooks into every part of the application lifecycle.
- **CLI Suite**: Extensive command-line tools for migrations, cache management, scaffolding, serving, diagnostics and more.

### Content & Data
- **Flexible Content Drivers**: Choose the storage strategy that fits your scale.
  | Driver | Use Case | Storage |
  |--------|----------|---------|
  | **File** | Small sites, simple setup | Markdown files |
  | **DB** | Large sites, complex queries | Database only |
  | **Hybrid** | Best of both | Markdown + DB metadata |
  | **Auto** | Automatic switching | Starts File, switches to Hybrid at 100 pages - configurable! |
- **Fluent Query Builder**: Expressive database abstraction layer supporting complex queries, joins, raw expressions, and automatic caching.
- **Schema Builder & Migrations**:
  - **Database Agnostic**: Write schemas once, run on SQLite, MySQL, or PostgreSQL.
  - **Fluent Interface**: Define tables and columns with an expressive syntax (`$table->string('title')->nullable()`).
  - **Migration System**: Version control for your database schema with `up` and `down` methods.
- **Pluggable Markdown Engine**:
  - **Drivers**: Support for `CommonMark` (default), `Parsedown`, or simple `HTML` pass-through.
  - **Extensible**: Custom template tags (`{{ }}`, `{!! !!}`) preserved in all drivers.
  - **CommonMark Features**: Tables, Strikethrough, Autolink, Task Lists (via extensions).
- **Velvet Content Blocks**: `.vlt` files with YAML frontmatter and block switches (`@markdown`, `@html`, `@text`).
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
  - Control structures (`@if`, `@foreach`)
  - Layout inheritance (`@extends`, `@section`, `@yield`)
  - Partials and includes (`@include`)
  - Namespace support for modular views
- **Asset Server**: Built-in static asset serving for development and production, handling MIME types and module assets efficiently.
- **Security Suite**: Built-in protection against common vectors.
  - ✅ **XSS Protection** - Auto-escaping template engine
  - ✅ **CSRF Protection** - Token-based validation
  - ✅ **SQL Injection** - Prepared statements everywhere
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
- **Tenant-aware paths** (when enabled):
  - Content: user/tenants/<tenant>/content
  - Views: user/tenants/<tenant>/views
  - Storage: storage/tenants/<tenant>
  - Modules: user/tenants/<tenant>/modules
- **Isolation**: Cache prefixing and tenant-scoped storage prevent cross-tenant collisions.
- **Sessions**: Path-based tenancy scopes cookies to the tenant path automatically.
- **CLI**: Set a tenant via $TENANCY_TENANT (or $TENANT) for commands.

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
composer install

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
- [Getting Started](https://velvetcms.com/docs/core/getting-started/overview)
- [Install & Bootstrap](https://velvetcms.com/docs/core/getting-started/installation)
- [Directory Structure](https://velvetcms.com/docs/core/getting-started/directory-structure)

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