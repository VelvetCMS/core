<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

interface FilesystemInterface
{
    public function exists(string $path): bool;

    public function get(string $path): ?string;

    /** @param string|resource $contents */
    public function put(string $path, mixed $contents, array $config = []): bool;

    public function delete(string $path): bool;

    public function makeDirectory(string $path): bool;

    public function size(string $path): int;

    public function lastModified(string $path): int;

    public function files(string $directory, bool $recursive = false): array;

    /** @throws \RuntimeException If the disk does not support URLs. */
    public function url(string $path): string;

    public function path(string $path): string;
}
