<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

/**
 * Simple key-value storage with collections.
 * 
 * For complex queries, use Connection directly.
 * This is for settings, menus, small config-like data.
 */
interface DataStore
{
    public function get(string $collection, string $key): ?array;

    public function put(string $collection, string $key, array $data): void;

    public function forget(string $collection, string $key): bool;

    public function has(string $collection, string $key): bool;

    public function all(string $collection): array;

    /**
     * @param callable(array): bool $predicate
     * @return array<string, array> Keyed by record key
     */
    public function filter(string $collection, callable $predicate): array;

    public function clear(string $collection): void;

    public function driver(): string;
}
