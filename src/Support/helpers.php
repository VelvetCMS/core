<?php

declare(strict_types=1);

if (!function_exists('uuid_v7')) {
    /**
     * Generate a UUIDv7 (time-ordered, random).
     *
     * Format: 8-4-4-4-12 hex string (36 chars).
     * First 48 bits = Unix timestamp in milliseconds (sortable).
     * Remaining bits = cryptographically random.
     */
    function uuid_v7(): string
    {
        $ms = (int) (microtime(true) * 1000);
        $rand = random_bytes(10);

        // Set version (0111 = 7) in bits 48-51
        $rand[0] = chr((ord($rand[0]) & 0x0F) | 0x70);
        // Set variant (10xx) in bits 64-65
        $rand[2] = chr((ord($rand[2]) & 0x3F) | 0x80);

        $hex = str_pad(dechex($ms), 12, '0', STR_PAD_LEFT) . bin2hex($rand);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}

if (!function_exists('is_uuid')) {
    /**
     * Check if a string is a valid UUID (any version).
     */
    function is_uuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string|array $key, mixed $default = null): mixed
    {
        $repository = app(\VelvetCMS\Core\ConfigRepository::class);

        if (is_array($key)) {
            foreach ($key as $innerKey => $value) {
                $repository->set($innerKey, $value);
            }
            return null;
        }

        return $repository->get($key, $default);
    }
}

if (!function_exists('tenant')) {
    function tenant(): ?\VelvetCMS\Core\Tenancy\TenantContext
    {
        return app(\VelvetCMS\Core\Tenancy\TenancyState::class)->current();
    }
}

if (!function_exists('tenant_id')) {
    function tenant_id(): ?string
    {
        return app(\VelvetCMS\Core\Tenancy\TenancyState::class)->currentId();
    }
}

if (!function_exists('tenant_enabled')) {
    function tenant_enabled(): bool
    {
        return app(\VelvetCMS\Core\Tenancy\TenancyState::class)->isEnabled();
    }
}

if (!function_exists('tenant_prefix')) {
    function tenant_prefix(): string
    {
        $prefix = tenant()?->urlPrefix();
        if (!is_string($prefix) || $prefix === '' || $prefix === '/') {
            return '';
        }

        return '/' . ltrim($prefix, '/');
    }
}

if (!function_exists('tenant_url')) {
    function tenant_url(string $path = ''): string
    {
        $prefix = tenant_prefix();
        $path = '/' . ltrim($path, '/');

        if ($prefix === '') {
            return $path;
        }

        if ($path === '/') {
            return $prefix;
        }

        return rtrim($prefix, '/') . $path;
    }
}

if (!function_exists('tenant_user_path')) {
    function tenant_user_path(string $path = ''): string
    {
        return velvet_paths()->tenantUser($path);
    }
}

if (!function_exists('tenant_storage_path')) {
    function tenant_storage_path(string $path = ''): string
    {
        return velvet_paths()->tenantStorage($path);
    }
}

if (!function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $app = \VelvetCMS\Core\Application::getInstance();

        return $abstract === null ? $app : $app->make($abstract);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $path = app('router')->url($name, $params);
        $prefix = tenant_prefix();

        if ($prefix === '') {
            return $path;
        }

        if ($path === '/') {
            return $prefix;
        }

        return rtrim($prefix, '/') . $path;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \VelvetCMS\Http\Response
    {
        return \VelvetCMS\Http\Response::redirect($url, $status);
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        $manager = app('session');

        if ($key === null) {
            return $manager;
        }

        return $manager->get($key, $default);
    }
}

if (!function_exists('db')) {
    function db(): \VelvetCMS\Database\Connection
    {
        return app('db');
    }
}

if (!function_exists('request')) {
    function request(): \VelvetCMS\Http\Request
    {
        if (!isset($GLOBALS['__velvet_request']) || !$GLOBALS['__velvet_request'] instanceof \VelvetCMS\Http\Request) {
            $GLOBALS['__velvet_request'] = \VelvetCMS\Http\Request::capture();
        }

        return $GLOBALS['__velvet_request'];
    }
}

if (!function_exists('response')) {
    function response(string $content = '', int $status = 200): \VelvetCMS\Http\Response
    {
        return new \VelvetCMS\Http\Response($content, $status);
    }
}

if (!function_exists('velvet_paths')) {
    function velvet_paths(): \VelvetCMS\Core\Paths
    {
        if (\VelvetCMS\Core\Application::hasInstance()) {
            return \VelvetCMS\Core\Application::getInstance()->make(\VelvetCMS\Core\Paths::class);
        }

        return \VelvetCMS\Core\Paths::fromBootstrapEnvironment();
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return velvet_paths()->base($path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return velvet_paths()->publicPath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return velvet_paths()->storage($path);
    }
}

if (!function_exists('content_path')) {
    function content_path(string $path = ''): string
    {
        return velvet_paths()->content($path);
    }
}

if (!function_exists('view_path')) {
    function view_path(string $path = ''): string
    {
        $viewPath = config('view.path', 'user/views');
        if (!is_string($viewPath) || trim($viewPath) === '') {
            $viewPath = 'user/views';
        }

        $paths = velvet_paths();

        if (\VelvetCMS\Core\Paths::isAbsolute($viewPath)) {
            return \VelvetCMS\Core\Paths::join(rtrim($viewPath, '/\\'), $path);
        }

        $normalized = trim($viewPath, '/\\');

        if (tenant_enabled() && tenant_id() !== null) {
            if ($normalized === 'user') {
                return \VelvetCMS\Core\Paths::join($paths->tenantUser(), $path);
            }

            if (str_starts_with($normalized, 'user/')) {
                return \VelvetCMS\Core\Paths::join($paths->tenantUser(substr($normalized, strlen('user/'))), $path);
            }
        }

        return \VelvetCMS\Core\Paths::join($paths->base($normalized), $path);
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return velvet_paths()->config($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return tenant_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return app('view')->render($template, $data);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        exit(1);
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        echo $message ?: "Error {$code}";
        exit(1);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return $_SESSION['_token'] ??= bin2hex(random_bytes(32));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('now')) {
    function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'UTC')));
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8', false);
    }
}

if (!function_exists('raw')) {
    /** WARNING: Never pass unsanitized user input. */
    function raw(string $expression, array $bindings = []): \VelvetCMS\Database\RawExpression
    {
        return new \VelvetCMS\Database\RawExpression($expression, $bindings);
    }
}

if (!function_exists('sanitize_slug')) {
    /**
     * Sanitize a page slug to prevent path traversal.
     * Allows letters, numbers, dashes, underscores, and forward slashes.
     */
    function sanitize_slug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        $slug = str_replace('\\', '/', $slug);
        $slug = preg_replace('#/+#', '/', $slug);
        $slug = trim($slug, '/');

        if ($slug === '' || str_contains($slug, '..')) {
            return '';
        }

        if (!preg_match('#^[A-Za-z0-9/_-]+$#', $slug)) {
            return '';
        }

        return $slug;
    }
}

if (!function_exists('split_command_args')) {
    /**
     * Split a CLI command string into arguments, respecting simple quotes.
     * @return string[]
     */
    function split_command_args(string $command): array
    {
        $command = trim($command);
        if ($command === '') {
            return [];
        }

        $pattern = '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'|(\\S+)/';
        preg_match_all($pattern, $command, $matches, PREG_SET_ORDER);

        $args = [];
        foreach ($matches as $match) {
            $arg = $match[1] ?? ($match[2] ?? ($match[3] ?? ''));
            $args[] = stripcslashes($arg);
        }

        return $args;
    }
}

if (!function_exists('build_cli_command')) {
    /**
     * Build a shell-safe CLI command from binary, script, and command string.
     */
    function build_cli_command(string $binary, string $script, string $command): string
    {
        $args = array_merge([$binary, $script], split_command_args($command));
        return implode(' ', array_map('escapeshellarg', $args));
    }
}

if (!function_exists('build_cli_command_prefix')) {
    /**
     * Build a shell-safe CLI command from a prefix (e.g. "php velvet") and command string.
     */
    function build_cli_command_prefix(string $prefix, string $command): string
    {
        $args = array_merge(split_command_args($prefix), split_command_args($command));
        return implode(' ', array_map('escapeshellarg', $args));
    }
}
