<?php

declare(strict_types=1);

namespace VelvetCMS\Services;

use VelvetCMS\Contracts\FilesystemInterface;
use VelvetCMS\Drivers\Storage\LocalDriver;
use InvalidArgumentException;

class StorageManager
{
    private array $disks = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a filesystem instance.
     */
    public function disk(?string $name = null): FilesystemInterface
    {
        $name = $name ?? $this->config['default'] ?? 'local';

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->resolve($name);
        }

        return $this->disks[$name];
    }

    /**
     * Resolve a disk instance.
     */
    private function resolve(string $name): FilesystemInterface
    {
        $config = $this->config['disks'][$name] ?? null;

        if ($config === null) {
            throw new InvalidArgumentException("Storage disk [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? 'local';

        return match ($driver) {
            'local' => new LocalDriver($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] is not supported."),
        };
    }

    /**
     * Dynamically pass methods to the default disk.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->disk()->$method(...$parameters);
    }
}
