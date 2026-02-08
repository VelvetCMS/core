<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Database;

use ReflectionClass;
use VelvetCMS\Database\Connection;
use VelvetCMS\Database\QueryBuilder;
use VelvetCMS\Tests\Support\TestCase;

final class QueryBuilderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $this->tmpDir . '/test.sqlite',
                ],
            ],
        ];

        $this->connection = new Connection($config);

        // Create test table
        $this->connection->statement('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT UNIQUE,
            status TEXT DEFAULT "active",
            score INTEGER DEFAULT 0,
            created_at TEXT
        )');

        $this->connection->statement('CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            title TEXT,
            views INTEGER DEFAULT 0
        )');
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }

    private function bindings(QueryBuilder $qb): array
    {
        $ref = new ReflectionClass($qb);
        $prop = $ref->getProperty('bindings');
        return $prop->getValue($qb);
    }

    // === SELECT Tests ===

    public function test_basic_select(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->select('id', 'name')
            ->toSql();

        $this->assertSame('SELECT id, name FROM users', $sql);
    }

    public function test_select_with_array(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->select(['id', 'name', 'email'])
            ->toSql();

        $this->assertSame('SELECT id, name, email FROM users', $sql);
    }

    public function test_select_raw(): void
    {
        $qb = $this->builder()
            ->table('users')
            ->selectRaw('COUNT(*) as total, AVG(score) as avg_score');

        $this->assertStringContainsString('COUNT(*) as total', $qb->toSql());
    }

    // === WHERE Tests ===

    public function test_where_with_equals_operator(): void
    {
        $qb = $this->builder()
            ->table('users')
            ->where('status', '=', 'active');

        $this->assertSame('SELECT * FROM users WHERE status = ?', $qb->toSql());
        $this->assertSame(['active'], $this->bindings($qb));
    }

    public function test_where_with_comparison_operator(): void
    {
        $qb = $this->builder()
            ->table('users')
            ->where('score', '>', 100);

        $this->assertSame('SELECT * FROM users WHERE score > ?', $qb->toSql());
        $this->assertSame([100], $this->bindings($qb));
    }

    public function test_or_where(): void
    {
        $qb = $this->builder()
            ->table('users')
            ->where('status', '=', 'active')
            ->orWhere('status', '=', 'pending');

        $this->assertStringContainsString('OR status = ?', $qb->toSql());
    }

    public function test_where_in(): void
    {
        $qb = $this->builder()
            ->table('users')
            ->whereIn('id', [1, 2, 3]);

        $this->assertSame('SELECT * FROM users WHERE id IN (?, ?, ?)', $qb->toSql());
        $this->assertSame([1, 2, 3], $this->bindings($qb));
    }

    public function test_where_null(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->whereNull('email')
            ->toSql();

        $this->assertSame('SELECT * FROM users WHERE email IS NULL', $sql);
    }

    public function test_where_not_null(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->whereNotNull('email')
            ->toSql();

        $this->assertSame('SELECT * FROM users WHERE email IS NOT NULL', $sql);
    }

    public function test_where_raw(): void
    {
        $qb = $this->builder()
            ->table('users')
            ->whereRaw('score > ? AND score < ?', [50, 100]);

        $this->assertStringContainsString('score > ? AND score < ?', $qb->toSql());
        $this->assertSame([50, 100], $this->bindings($qb));
    }

    // === JOIN Tests ===

    public function test_inner_join(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->toSql();

        $this->assertStringContainsString('INNER JOIN posts ON users.id = posts.user_id', $sql);
    }

    public function test_left_join(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->toSql();

        $this->assertStringContainsString('LEFT JOIN posts ON users.id = posts.user_id', $sql);
    }

    // === GROUP BY / HAVING Tests ===

    public function test_group_by(): void
    {
        $sql = $this->builder()
            ->table('posts')
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->toSql();

        $this->assertStringContainsString('GROUP BY user_id', $sql);
    }

    public function test_having(): void
    {
        $qb = $this->builder()
            ->table('posts')
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->having('post_count', '>', 5);

        $this->assertStringContainsString('HAVING post_count > ?', $qb->toSql());
        $this->assertContains(5, $this->bindings($qb));
    }

    // === ORDER / LIMIT / OFFSET Tests ===

    public function test_order_by(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->orderBy('name', 'ASC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function test_order_by_desc(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->orderBy('created_at', 'DESC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function test_order_by_validates_column(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()
            ->table('users')
            ->orderBy('name; DROP TABLE users;--')
            ->toSql();
    }

    public function test_limit_and_offset(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->limit(10)
            ->offset(20)
            ->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    // === INSERT Tests ===

    public function test_insert(): void
    {
        $result = $this->builder()
            ->table('users')
            ->insert([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'score' => 100,
            ]);

        $this->assertTrue($result);

        $user = $this->builder()->table('users')->where('email', '=', 'john@example.com')->first();
        $this->assertSame('John Doe', $user['name']);
    }

    // === UPDATE Tests ===

    public function test_update(): void
    {
        $this->builder()->table('users')->insert([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $affected = $this->builder()
            ->table('users')
            ->where('email', '=', 'jane@example.com')
            ->update(['name' => 'Jane Updated']);

        $this->assertGreaterThanOrEqual(1, $affected);

        $user = $this->builder()->table('users')->where('email', '=', 'jane@example.com')->first();
        $this->assertSame('Jane Updated', $user['name']);
    }

    // === DELETE Tests ===

    public function test_delete(): void
    {
        $this->builder()->table('users')->insert([
            'name' => 'ToDelete',
            'email' => 'delete@example.com',
        ]);

        $affected = $this->builder()
            ->table('users')
            ->where('email', '=', 'delete@example.com')
            ->delete();

        $this->assertGreaterThanOrEqual(1, $affected);

        $user = $this->builder()->table('users')->where('email', '=', 'delete@example.com')->first();
        $this->assertNull($user);
    }

    // === Upsert Tests ===

    public function test_upsert_inserts_new_record(): void
    {
        $result = $this->builder()
            ->table('users')
            ->upsert(
                ['name' => 'Upsert User', 'email' => 'upsert@example.com', 'score' => 50],
                'email'
            );

        $this->assertTrue($result);

        $user = $this->builder()->table('users')->where('email', '=', 'upsert@example.com')->first();
        $this->assertSame('Upsert User', $user['name']);
    }

    public function test_upsert_updates_existing_record(): void
    {
        $this->builder()->table('users')->insert([
            'name' => 'Original',
            'email' => 'upsert2@example.com',
            'score' => 10,
        ]);

        $this->builder()
            ->table('users')
            ->upsert(
                ['name' => 'Updated', 'email' => 'upsert2@example.com', 'score' => 99],
                'email',
                ['name', 'score']
            );

        $user = $this->builder()->table('users')->where('email', '=', 'upsert2@example.com')->first();
        $this->assertSame('Updated', $user['name']);
        $this->assertSame(99, (int) $user['score']);
    }

    // === Aggregation Tests ===

    public function test_count(): void
    {
        $this->builder()->table('users')->insert(['name' => 'Count1', 'email' => 'c1@test.com']);
        $this->builder()->table('users')->insert(['name' => 'Count2', 'email' => 'c2@test.com']);

        $count = $this->builder()->table('users')->count();

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_exists_returns_true_when_records_exist(): void
    {
        $this->builder()->table('users')->insert(['name' => 'Exists', 'email' => 'exists@test.com']);

        $exists = $this->builder()
            ->table('users')
            ->where('email', '=', 'exists@test.com')
            ->exists();

        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_no_records(): void
    {
        $exists = $this->builder()
            ->table('users')
            ->where('email', '=', 'nonexistent@test.com')
            ->exists();

        $this->assertFalse($exists);
    }

    // === Result Tests ===

    public function test_first_returns_single_row(): void
    {
        $this->builder()->table('users')->insert(['name' => 'First', 'email' => 'first@test.com']);

        $user = $this->builder()->table('users')->where('email', '=', 'first@test.com')->first();

        $this->assertIsArray($user);
        $this->assertSame('First', $user['name']);
    }

    public function test_first_returns_null_when_empty(): void
    {
        $result = $this->builder()
            ->table('users')
            ->where('email', '=', 'nobody@nowhere.com')
            ->first();

        $this->assertNull($result);
    }

    public function test_get_returns_collection(): void
    {
        $this->builder()->table('users')->insert(['name' => 'Get1', 'email' => 'get1@test.com']);
        $this->builder()->table('users')->insert(['name' => 'Get2', 'email' => 'get2@test.com']);

        $results = $this->builder()
            ->table('users')
            ->whereIn('email', ['get1@test.com', 'get2@test.com'])
            ->get();

        $this->assertInstanceOf(\VelvetCMS\Database\Collection::class, $results);
        $this->assertSame(2, $results->count());
    }

    public function test_find_by_id(): void
    {
        $this->builder()->table('users')->insert(['name' => 'FindMe', 'email' => 'find@test.com']);

        $user = $this->builder()->table('users')->where('email', '=', 'find@test.com')->first();
        $found = $this->builder()->table('users')->find($user['id']);

        $this->assertSame('FindMe', $found['name']);
    }

    public function test_pluck_returns_single_column(): void
    {
        $this->builder()->table('users')->insert(['name' => 'Pluck1', 'email' => 'pluck1@test.com']);
        $this->builder()->table('users')->insert(['name' => 'Pluck2', 'email' => 'pluck2@test.com']);

        $names = $this->builder()
            ->table('users')
            ->whereIn('email', ['pluck1@test.com', 'pluck2@test.com'])
            ->pluck('name');

        $this->assertContains('Pluck1', $names->all());
        $this->assertContains('Pluck2', $names->all());
    }

    // === Pagination Tests ===

    public function test_paginate_returns_correct_structure(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->builder()->table('users')->insert([
                'name' => "User{$i}",
                'email' => "page{$i}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate(3, 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('last_page', $result);

        $this->assertSame(3, $result['per_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame(10, $result['total']);
        $this->assertSame(4, $result['last_page']);
        $this->assertCount(3, $result['data']->all());
    }

    public function test_paginate_second_page(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->builder()->table('users')->insert([
                'name' => "PgUser{$i}",
                'email' => "pg{$i}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate(2, 2);

        $this->assertSame(2, $result['current_page']);
        $this->assertSame(3, $result['last_page']);
        $this->assertCount(2, $result['data']->all());
    }

    public function test_paginate_last_page_partial(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->builder()->table('users')->insert([
                'name' => "Last{$i}",
                'email' => "last{$i}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate(3, 3);

        $this->assertSame(3, $result['current_page']);
        $this->assertSame(3, $result['last_page']);
        $this->assertCount(1, $result['data']->all());
    }

    public function test_paginate_empty_table(): void
    {
        $result = $this->builder()->table('posts')->paginate(10, 1);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['last_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertCount(0, $result['data']->all());
    }

    public function test_paginate_with_where_clause(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->builder()->table('users')->insert([
                'name' => "Filtered{$i}",
                'email' => "filter{$i}@test.com",
                'status' => $i <= 4 ? 'active' : 'inactive',
            ]);
        }

        $result = $this->builder()
            ->table('users')
            ->where('status', '=', 'active')
            ->paginate(2, 1);

        $this->assertSame(4, $result['total']);
        $this->assertSame(2, $result['last_page']);
        $this->assertCount(2, $result['data']->all());
    }

    public function test_paginate_clamps_page_to_minimum_one(): void
    {
        $this->builder()->table('users')->insert([
            'name' => 'Clamp',
            'email' => 'clamp@test.com',
        ]);

        $result = $this->builder()->table('users')->paginate(10, 0);

        $this->assertSame(1, $result['current_page']);
    }

    public function test_paginate_defaults(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->builder()->table('users')->insert([
                'name' => "Def{$i}",
                'email' => "def{$i}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate();

        $this->assertSame(15, $result['per_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertCount(15, $result['data']->all());
        $this->assertSame(2, $result['last_page']);
    }
}
