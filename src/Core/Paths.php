<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

use VelvetCMS\Core\Tenancy\TenancyState;

final class Paths
{
    private readonly string $basePath;
    private readonly ?TenancyState $tenancyState;

    public function __construct(string $basePath, ?TenancyState $tenancyState = null)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->tenancyState = $tenancyState;
    }

    public static function fromBootstrapEnvironment(): self
    {
        $basePath = $_ENV['VELVET_BASE_PATH'] ?? $_SERVER['VELVET_BASE_PATH'] ?? getenv('VELVET_BASE_PATH');

        if (!is_string($basePath) || $basePath === '') {
            if (defined('VELVET_BASE_PATH') && is_string(VELVET_BASE_PATH) && VELVET_BASE_PATH !== '') {
                $basePath = VELVET_BASE_PATH;
            } else {
                $basePath = dirname(__DIR__, 2);
            }
        }

        return new self($basePath);
    }

    public function base(string $path = ''): string
    {
        return self::join($this->basePath, $path);
    }

    public function publicPath(string $path = ''): string
    {
        return self::join($this->base('public'), $path);
    }

    public function config(string $path = ''): string
    {
        return self::join($this->base('config'), $path);
    }

    public function user(string $path = ''): string
    {
        return self::join($this->base('user'), $path);
    }

    public function tenantUser(string $path = ''): string
    {
        if ($this->tenancyState === null || !$this->tenancyState->isEnabled() || $this->tenancyState->currentId() === null) {
            return $this->user($path);
        }

        $root = (string) ($this->tenancyState->config()['paths']['user_root'] ?? 'user/tenants');
        $tenantRoot = self::join($this->resolveConfiguredRoot($root), (string) $this->tenancyState->currentId());

        return self::join($tenantRoot, $path);
    }

    public function tenantStorage(string $path = ''): string
    {
        if ($this->tenancyState === null || !$this->tenancyState->isEnabled() || $this->tenancyState->currentId() === null) {
            return self::join($this->base('storage'), $path);
        }

        $root = (string) ($this->tenancyState->config()['paths']['storage_root'] ?? 'storage/tenants');
        $tenantRoot = self::join($this->resolveConfiguredRoot($root), (string) $this->tenancyState->currentId());

        return self::join($tenantRoot, $path);
    }

    public function storage(string $path = ''): string
    {
        return self::join($this->tenantStorage(), $path);
    }

    public function content(string $path = ''): string
    {
        return self::join($this->tenantUser('content'), $path);
    }

    public static function join(string $basePath, string $path = ''): string
    {
        if ($path === '') {
            return $basePath;
        }

        if (self::isAbsolute($path)) {
            return rtrim($path, '/\\');
        }

        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public static function isAbsolute(string $path): bool
    {
        return (bool) preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\|\/)/', $path);
    }

    private function resolveConfiguredRoot(string $path): string
    {
        if ($path === '') {
            return $this->base();
        }

        if (self::isAbsolute($path)) {
            return rtrim($path, '/\\');
        }

        return $this->base(trim($path, '/\\'));
    }
}
