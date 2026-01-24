<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Data;

use VelvetCMS\Contracts\DataStore;
use VelvetCMS\Database\Connection;
use VelvetCMS\Database\Schema\Blueprint;
use VelvetCMS\Database\Schema\Schema;

class DatabaseDataStore implements DataStore
{
    private const TABLE = 'data_store';
    
    private bool $tableExists;

    public function __construct(
        private readonly Connection $db
    ) {
        $this->tableExists = $this->ensureTable();
    }

    public function get(string $collection, string $key): ?array
    {
        if (!$this->tableExists) {
            return null;
        }

        $row = $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->where('key', '=', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        $data = json_decode($row['data'] ?? '{}', true);
        
        return is_array($data) ? $data : null;
    }

    public function put(string $collection, string $key, array $data): void
    {
        if (!$this->tableExists) {
            return;
        }

        $data['_key'] = $key;
        $data['_updated_at'] = date('Y-m-d H:i:s');
        $data['_created_at'] ??= $data['_updated_at'];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');

        $exists = $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->where('key', '=', $key)
            ->exists();

        if ($exists) {
            $this->db->table(self::TABLE)
                ->where('collection', '=', $collection)
                ->where('key', '=', $key)
                ->update([
                    'data' => $json,
                    'updated_at' => $now,
                ]);
        } else {
            $this->db->table(self::TABLE)->insert([
                'collection' => $collection,
                'key' => $key,
                'data' => $json,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function forget(string $collection, string $key): bool
    {
        if (!$this->tableExists) {
            return false;
        }

        $deleted = $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->where('key', '=', $key)
            ->delete();

        return $deleted > 0;
    }

    public function has(string $collection, string $key): bool
    {
        if (!$this->tableExists) {
            return false;
        }

        return $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->where('key', '=', $key)
            ->exists();
    }

    public function all(string $collection): array
    {
        if (!$this->tableExists) {
            return [];
        }

        $rows = $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->get();

        $records = [];
        
        foreach ($rows as $row) {
            $data = json_decode($row['data'] ?? '{}', true);
            if (is_array($data)) {
                $records[$row['key']] = $data;
            }
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
        if (!$this->tableExists) {
            return;
        }

        $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->delete();
    }

    public function driver(): string
    {
        return 'database';
    }

    private function ensureTable(): bool
    {
        try {
            if ($this->db->tableExists(self::TABLE)) {
                return true;
            }

            Schema::setConnection($this->db);

            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->id();
                $table->string('collection', 100);
                $table->string('key', 255);
                $table->text('data');
                $table->timestamp('created_at');
                $table->timestamp('updated_at');
                $table->unique(['collection', 'key']);
                $table->index('collection');
            });

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
