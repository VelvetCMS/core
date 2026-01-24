<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Database;

use ReflectionClass;
use VelvetCMS\Database\Connection;
use VelvetCMS\Database\QueryBuilder;
use VelvetCMS\Tests\Support\TestCase;

final class QueryBuilderTest extends TestCase
{
    private function makeBuilder(): QueryBuilder
    {
        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $this->tmpDir . '/db.sqlite',
                ],
            ],
        ];

        return new QueryBuilder(new Connection($config));
    }

    public function test_basic_select_where_order_limit(): void
    {
        $qb = $this->makeBuilder()
            ->table('pages')
            ->select('id', 'slug')
            ->where('status', '=', 'published')
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->offset(5);

        $this->assertSame('SELECT id, slug FROM pages WHERE status = ? ORDER BY created_at DESC LIMIT 10 OFFSET 5', $qb->toSql());
        $this->assertSame(['published'], $this->bindings($qb));
    }

    public function test_where_in_builds_placeholders(): void
    {
        $qb = $this->makeBuilder()
            ->table('pages')
            ->whereIn('id', [1, 2, 3]);

        $this->assertSame('SELECT * FROM pages WHERE id IN (?, ?, ?)', $qb->toSql());
        $this->assertSame([1, 2, 3], $this->bindings($qb));
    }

    private function bindings(QueryBuilder $qb): array
    {
        $ref = new ReflectionClass($qb);
        $prop = $ref->getProperty('bindings');
        $prop->setAccessible(true);
        return $prop->getValue($qb);
    }
}
