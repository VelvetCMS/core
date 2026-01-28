<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Storage;

use RuntimeException;
use VelvetCMS\Contracts\FilesystemInterface;

class LocalDriver implements FilesystemInterface
{
    private string $root;
    private ?string $url;
    private int $permissions;

    public function __construct(array $config)
    {
        $this->root = $this->ensureRootDirectory($config['root']);
        $this->url = isset($config['url']) ? rtrim($config['url'], '/') : null;
        $this->permissions = $config['permissions'] ?? 0755;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->addPath($path));
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->addPath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath) ?: null;
    }

    public function put(string $path, mixed $contents, array $config = []): bool
    {
        $fullPath = $this->addPath($path);
        $this->ensureDirectoryExists(dirname($fullPath));

        if (is_resource($contents)) {
            $stream = fopen($fullPath, 'w');
            if (!$stream) {
                return false;
            }

            while (!feof($contents)) {
                fwrite($stream, fread($contents, 8192));
            }

            fclose($stream);
            return true;
        }

        return file_put_contents($fullPath, $contents) !== false;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->addPath($path);

        if (!file_exists($fullPath)) {
            return true;
        }

        return unlink($fullPath);
    }

    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->addPath($path);
        return $this->ensureDirectoryExists($fullPath);
    }

    public function size(string $path): int
    {
        return filesize($this->addPath($path)) ?: 0;
    }

    public function lastModified(string $path): int
    {
        return filemtime($this->addPath($path)) ?: 0;
    }

    public function files(string $directory, bool $recursive = false): array
    {
        $dir = $this->addPath($directory);

        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            $relativePath = ltrim($directory . '/' . $item, '/');

            if (is_dir($fullPath)) {
                if ($recursive) {
                    $files = array_merge($files, $this->files($relativePath, true));
                }
            } else {
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    public function url(string $path): string
    {
        if ($this->url === null) {
            throw new RuntimeException('This disk does not maintain a public URL.');
        }

        return $this->url . '/' . ltrim($path, '/');
    }

    public function path(string $path): string
    {
        return $this->addPath($path);
    }

    private function ensureRootDirectory(string $path): string
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return rtrim(realpath($path), DIRECTORY_SEPARATOR);
    }

    protected function addPath(string $path): string
    {
        // Sanitize path to prevent traversal
        $path = str_replace(['\\', '//'], '/', $path);

        // Remove '..' segments to prevent escaping root
        $parts = array_filter(explode('/', $path), function ($part) {
            return $part !== '..' && $part !== '.';
        });

        return $this->root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function ensureDirectoryExists(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, $this->permissions, true);
    }
}
