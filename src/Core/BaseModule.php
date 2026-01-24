<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

use VelvetCMS\Contracts\Module;
abstract class BaseModule implements Module
{
    protected string $basePath;
    protected array $manifest;
    protected ModuleManifest $manifestObject;
    protected array $migrationPaths = [];

    public function __construct(string $basePath, ModuleManifest $manifest)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->manifestObject = $manifest;
        $this->manifest = $manifest->toArray();
    }

    public function getMigrationPaths(): array
    {
        $default = $this->path('database/migrations');
        $paths = is_dir($default) ? [$default] : [];
        
        return array_merge($paths, $this->migrationPaths);
    }

    public function path(string $path = ''): string
    {
        return $path ? $this->basePath . '/' . ltrim($path, '/') : $this->basePath;
    }

    public function publicPath(): ?string
    {
        $path = $this->path('public');
        return is_dir($path) ? $path : null;
    }

    public function assetsPrefix(): ?string
    {
        return $this->name();
    }

    public function name(): string
    {
        return $this->manifestObject->name;
    }

    public function version(): string
    {
        return $this->manifestObject->version;
    }

    public function description(): string
    {
        return $this->manifestObject->description ?? ($this->manifest['description'] ?? '');
    }

    public function manifestObject(): ModuleManifest
    {
        return $this->manifestObject;
    }

    public function manifestConfig(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->manifest)) {
            return $this->manifest[$key];
        }

        $extra = $this->manifest['extra'] ?? [];
        if (array_key_exists($key, $extra)) {
            return $extra[$key];
        }

        return $default;
    }

    public function register(Application $app): void {}

    public function boot(Application $app): void {}

    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!file_exists($path)) {
            return;
        }
        
        $moduleConfig = require $path;
        if (!is_array($moduleConfig)) {
            return;
        }

        $currentConfig = config($key, []);
        $merged = array_replace_recursive($moduleConfig, $currentConfig);
        
        config([$key => $merged]);
    }

    protected function loadRoutesFrom(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $router = app('router');
        require $path;
    }

    protected function loadViewsFrom(string $path, string $namespace): void
    {
        if (!is_dir($path)) {
            return;
        }
        
        app('view')->namespace($namespace, $path);
    }

    protected function loadMigrationsFrom(string $path): void
    {
        if (property_exists($this, 'migrationPaths')) {
            $this->migrationPaths[] = $path;
        }
    }
}
