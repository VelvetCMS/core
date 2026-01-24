<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Data;

use VelvetCMS\Contracts\DataStore;
use VelvetCMS\Database\Connection;

class AutoDataStore implements DataStore
{
    private FileDataStore $fileStore;
    private ?DatabaseDataStore $dbStore = null;
    private bool $dbAvailable = false;

    public function __construct(
        ?Connection $connection = null,
        ?string $fileBasePath = null
    ) {
        $this->fileStore = new FileDataStore($fileBasePath);

        if ($connection !== null) {
            try {
                $connection->getPdo();
                $this->dbStore = new DatabaseDataStore($connection);
                $this->dbAvailable = true;
            } catch (\Throwable) {
                $this->dbAvailable = false;
            }
        }
    }

    public function get(string $collection, string $key): ?array
    {
        if ($this->dbAvailable) {
            $data = $this->dbStore->get($collection, $key);
            if ($data !== null) {
                return $data;
            }
        }

        return $this->fileStore->get($collection, $key);
    }

    public function put(string $collection, string $key, array $data): void
    {
        $this->fileStore->put($collection, $key, $data);

        if ($this->dbAvailable) {
            $this->dbStore->put($collection, $key, $data);
        }
    }

    public function forget(string $collection, string $key): bool
    {
        $fileResult = $this->fileStore->forget($collection, $key);
        
        if ($this->dbAvailable) {
            $dbResult = $this->dbStore->forget($collection, $key);
            return $fileResult || $dbResult;
        }

        return $fileResult;
    }

    public function has(string $collection, string $key): bool
    {
        if ($this->dbAvailable && $this->dbStore->has($collection, $key)) {
            return true;
        }

        return $this->fileStore->has($collection, $key);
    }

    public function all(string $collection): array
    {
        $records = $this->fileStore->all($collection);

        if ($this->dbAvailable) {
            $dbRecords = $this->dbStore->all($collection);
            $records = array_merge($records, $dbRecords);
        }

        return $records;
    }

    public function filter(string $collection, callable $predicate): array
    {
        $all = $this->all($collection);
        
        return array_filter($all, $predicate);
    }

    public function clear(string $collection): void
    {
        $this->fileStore->clear($collection);

        if ($this->dbAvailable) {
            $this->dbStore->clear($collection);
        }
    }

    public function driver(): string
    {
        return 'auto';
    }

    public function isDatabaseAvailable(): bool
    {
        return $this->dbAvailable;
    }

    public function activeDriver(): string
    {
        return $this->dbAvailable ? 'database' : 'file';
    }

    public function fileStore(): FileDataStore
    {
        return $this->fileStore;
    }

    public function databaseStore(): ?DatabaseDataStore
    {
        return $this->dbStore;
    }
}
