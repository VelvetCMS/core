<?php

declare(strict_types=1);

namespace VelvetCMS\Content\Index;

use PDO;
use PDOException;

final class SqlitePageIndex implements PageIndex
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $path,
    ) {
    }

    public function get(string $slug): ?PageIndexEntry
    {
        $statement = $this->pdo()->prepare('SELECT * FROM page_index WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRowToEntry($row) : null;
    }

    public function put(PageIndexEntry $entry): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO page_index (
                slug, path, mtime, format, title, status, layout, excerpt, trusted,
                created_at, updated_at, published_at, meta_json
            ) VALUES (
                :slug, :path, :mtime, :format, :title, :status, :layout, :excerpt, :trusted,
                :created_at, :updated_at, :published_at, :meta_json
            )
            ON CONFLICT(slug) DO UPDATE SET
                path = excluded.path,
                mtime = excluded.mtime,
                format = excluded.format,
                title = excluded.title,
                status = excluded.status,
                layout = excluded.layout,
                excerpt = excluded.excerpt,
                trusted = excluded.trusted,
                created_at = excluded.created_at,
                updated_at = excluded.updated_at,
                published_at = excluded.published_at,
                meta_json = excluded.meta_json'
        );

        $statement->execute($this->entryBindings($entry));
    }

    public function delete(string $slug): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM page_index WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
    }

    public function query(?PageIndexQuery $query = null): array
    {
        $query ??= new PageIndexQuery();

        [$sql, $bindings] = $this->buildSelectSql($query, 'SELECT * FROM page_index');
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): PageIndexEntry => $this->mapRowToEntry($row), $rows);
    }

    public function count(?PageIndexQuery $query = null): int
    {
        $query ??= new PageIndexQuery();
        $bindings = [];
        $sql = 'SELECT COUNT(*) FROM page_index';

        if ($query->status !== null) {
            $sql .= ' WHERE status = :status';
            $bindings['status'] = $query->status;
        }

        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);

        return (int) $statement->fetchColumn();
    }

    public function sync(iterable $filesBySlug, PageIndexer $indexer): void
    {
        $existing = $this->loadExistingEntries();
        $seen = [];

        $this->pdo()->beginTransaction();

        try {
            foreach ($filesBySlug as $slug => $filepath) {
                $seen[$slug] = true;
                $mtime = filemtime($filepath) ?: 0;
                $entry = $existing[$slug] ?? null;

                if ($entry === null || $entry['mtime'] !== $mtime || $entry['path'] !== $filepath) {
                    $this->put($indexer->indexFile($slug, $filepath, $mtime));
                }
            }

            foreach (array_keys($existing) as $slug) {
                if (!isset($seen[$slug])) {
                    $this->delete($slug);
                }
            }

            $this->pdo()->commit();
        } catch (\Throwable $e) {
            $this->pdo()->rollBack();
            throw $e;
        }
    }

    public function rebuild(iterable $filesBySlug, PageIndexer $indexer): void
    {
        $this->pdo()->beginTransaction();

        try {
            $this->pdo()->exec('DELETE FROM page_index');

            foreach ($filesBySlug as $slug => $filepath) {
                $this->put($indexer->indexFile($slug, $filepath));
            }

            $this->pdo()->commit();
        } catch (\Throwable $e) {
            $this->pdo()->rollBack();
            throw $e;
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        try {
            $this->pdo = new PDO('sqlite:' . $this->path);
        } catch (PDOException $e) {
            throw new \RuntimeException('SQLite page index connection failed: ' . $e->getMessage(), previous: $e);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->initializeSchema();

        return $this->pdo;
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS page_index (
                slug TEXT PRIMARY KEY,
                path TEXT NOT NULL,
                mtime INTEGER NOT NULL,
                format TEXT NOT NULL,
                title TEXT NOT NULL,
                status TEXT NOT NULL,
                layout TEXT NULL,
                excerpt TEXT NULL,
                trusted INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                published_at TEXT NULL,
                meta_json TEXT NOT NULL DEFAULT \'{}\'
            )'
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_page_index_status ON page_index(status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_page_index_created_at ON page_index(created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_page_index_published_at ON page_index(published_at)');
    }

    /**
     * @return array<string, array{path: string, mtime: int}>
     */
    private function loadExistingEntries(): array
    {
        $rows = $this->pdo()->query('SELECT slug, path, mtime FROM page_index')->fetchAll();
        $existing = [];

        foreach ($rows as $row) {
            $existing[(string) $row['slug']] = [
                'path' => (string) $row['path'],
                'mtime' => (int) $row['mtime'],
            ];
        }

        return $existing;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSelectSql(PageIndexQuery $query, string $baseSql): array
    {
        $bindings = [];
        $sql = $baseSql;

        if ($query->status !== null) {
            $sql .= ' WHERE status = :status';
            $bindings['status'] = $query->status;
        }

        $orderBy = match ($query->orderBy) {
            'slug' => 'slug',
            'title' => 'title',
            'status' => 'status',
            'updated_at' => 'updated_at',
            'published_at' => 'published_at',
            default => 'created_at',
        };
        $direction = $query->orderDirection === 'asc' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$direction}, slug ASC";

        if ($query->limit !== null) {
            $sql .= ' LIMIT :limit';
            $bindings['limit'] = $query->limit;
        }

        if ($query->offset > 0) {
            if ($query->limit === null) {
                $sql .= ' LIMIT -1';
            }

            $sql .= ' OFFSET :offset';
            $bindings['offset'] = $query->offset;
        }

        return [$sql, $bindings];
    }

    private function mapRowToEntry(array $row): PageIndexEntry
    {
        $meta = json_decode((string) ($row['meta_json'] ?? '{}'), true);

        return new PageIndexEntry(
            slug: (string) $row['slug'],
            path: (string) $row['path'],
            mtime: (int) $row['mtime'],
            format: (string) $row['format'],
            title: (string) $row['title'],
            status: (string) $row['status'],
            layout: isset($row['layout']) ? (string) $row['layout'] : null,
            excerpt: isset($row['excerpt']) ? (string) $row['excerpt'] : null,
            trusted: (bool) ($row['trusted'] ?? false),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            publishedAt: isset($row['published_at']) ? (string) $row['published_at'] : null,
            meta: is_array($meta) ? $meta : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entryBindings(PageIndexEntry $entry): array
    {
        return [
            'slug' => $entry->slug,
            'path' => $entry->path,
            'mtime' => $entry->mtime,
            'format' => $entry->format,
            'title' => $entry->title,
            'status' => $entry->status,
            'layout' => $entry->layout,
            'excerpt' => $entry->excerpt,
            'trusted' => $entry->trusted ? 1 : 0,
            'created_at' => $entry->createdAt,
            'updated_at' => $entry->updatedAt,
            'published_at' => $entry->publishedAt,
            'meta_json' => json_encode($entry->meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
