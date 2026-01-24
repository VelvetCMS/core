<?php

declare(strict_types=1);

namespace VelvetCMS\Database;

use VelvetCMS\Contracts\CacheDriver;

class QueryBuilder
{
    private string $table;
    private array $wheres = [];
    private array $bindings = [];
    private array $selects = ['*'];
    private array $joins = [];
    private array $groupBy = [];
    private array $having = [];
    private ?string $orderBy = null;
    private ?string $orderDirection = 'ASC';
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private ?int $cacheTtl = null;
    
    public function __construct(
    private readonly Connection $connection,
    private readonly ?CacheDriver $cache = null
    ) {}
    
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }
    
    /** @param string|array|RawExpression ...$columns */
    public function select(string|array|RawExpression $columns = '*'): self
    {
        if ($columns instanceof RawExpression) {
            $this->selects = [$columns->getValue()];
            $this->bindings = array_merge($this->bindings, $columns->getBindings());
        } elseif (is_array($columns)) {
            $this->selects = array_map(function ($col) {
                if ($col instanceof RawExpression) {
                    $this->bindings = array_merge($this->bindings, $col->getBindings());
                    return $col->getValue();
                }
                return $col;
            }, $columns);
        } else {
            $this->selects = func_get_args();
        }
        return $this;
    }
    
    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->selects[] = $expression;
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }
    
    public function where(string|RawExpression $column, string $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $columnSql = $column instanceof RawExpression ? $column->getValue() : $column;
        
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $columnSql,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];
        
        if ($column instanceof RawExpression) {
            $this->bindings = array_merge($this->bindings, $column->getBindings());
        }
        $this->bindings[] = $value;
        
        return $this;
    }
    
    public function whereRaw(string $expression, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $expression,
            'boolean' => 'AND',
        ];
        
        $this->bindings = array_merge($this->bindings, $bindings);
        
        return $this;
    }
    
    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }
    
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];
        
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        
        return $this;
    }
    
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND',
        ];
        
        return $this;
    }
    
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND',
        ];
        
        return $this;
    }
    
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        
        return $this;
    }
    
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        
        return $this;
    }
    
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        
        return $this;
    }
    
    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }
    
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }
    
    public function orderBy(string|RawExpression $column, string $direction = 'ASC'): self
    {
        if ($column instanceof RawExpression) {
            $this->orderBy = $column->getValue();
            $this->bindings = array_merge($this->bindings, $column->getBindings());
        } else {
            // Security: Validate column name to prevent injection
            if (!preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
                throw new \InvalidArgumentException("Invalid column name for orderBy: {$column}. Use RawExpression for complex clauses.");
            }
            $this->orderBy = $column;
        }
        $this->orderDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        return $this;
    }
    
    public function orderByRaw(string $expression, array $bindings = []): self
    {
        $this->orderBy = $expression;
        $this->orderDirection = '';
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }
    
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }
    
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }
    
    public function cache(int $ttl = 300): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }
    
    public function get(): Collection
    {
        $sql = $this->toSql();
        
        if ($this->cacheTtl && $this->cache) {
            try {
                $cacheKey = 'query:' . md5($sql . serialize($this->bindings));
                $cached = $this->cache->get($cacheKey);
                
                if ($cached !== null) {
                    return new Collection($cached);
                }
            } catch (\Throwable $e) {
                // Cache read failed, continue to database query
            }
        }
        
        $results = $this->connection->query($sql, $this->bindings);
        
        if ($this->cacheTtl && $this->cache) {
            try {
                $this->cache->set($cacheKey, $results, $this->cacheTtl);
            } catch (\Throwable $e) {
                // Cache write failed, but we have results from DB
            }
        }
        
        return new Collection($results);
    }
    
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results->first();
    }

    public function pluck(string $column): Collection
    {
        $results = $this->select($column)->get();
        
        return $results->map(function ($row) use ($column) {
            return $row[$column] ?? null;
        });
    }
    
    public function find(int|string $id): ?array
    {
        return $this->where('id', $id)->first();
    }
    
    public function count(): int
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) as count'];
        
        $result = $this->first();
        $this->selects = $originalSelects;
        
        return (int) ($result['count'] ?? 0);
    }

    public function exists(): bool
    {
        $originalSelects = $this->selects;
        $this->selects = ['1'];
        $this->limit(1);
        
        $result = $this->first();
        $this->selects = $originalSelects;
        
        return $result !== null;
    }
    
    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $this->connection->statement($sql, array_values($data));
        return true;
    }
    
    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];
        
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $this->buildWhere()
        );
        
        return $this->connection->statement($sql, array_merge($bindings, $this->bindings));
    }
    
    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->table,
            $this->buildWhere()
        );
        
        return $this->connection->statement($sql, $this->bindings);
    }
    
    public function toSql(): string
    {
        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $this->selects),
            $this->table
        );
        
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        
        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy} {$this->orderDirection}";
        }
        
        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }
        
        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }
        
        return $sql;
    }
    
    private function buildJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }
        
        $sql = '';
        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }
        
        return $sql;
    }
    
    private function buildGroupBy(): string
    {
        if (empty($this->groupBy)) {
            return '';
        }
        
        return ' GROUP BY ' . implode(', ', $this->groupBy);
    }
    
    private function buildHaving(): string
    {
        if (empty($this->having)) {
            return '';
        }
        
        $sql = ' HAVING ';
        $clauses = [];
        
        foreach ($this->having as $index => $condition) {
            $boolean = $index === 0 ? '' : " {$condition['boolean']} ";
            $clauses[] = $boolean . "{$condition['column']} {$condition['operator']} ?";
        }
        
        return $sql . implode('', $clauses);
    }
    
    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        
        $sql = ' WHERE ';
        $clauses = [];
        
        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";
            
            $clause = match ($where['type']) {
                'basic' => "{$where['column']} {$where['operator']} ?",
                'in' => "{$where['column']} IN (" . implode(', ', array_fill(0, count($where['values']), '?')) . ")",
                'null' => "{$where['column']} IS NULL",
                'not_null' => "{$where['column']} IS NOT NULL",
                'raw' => $where['sql'],
                default => ''
            };
            
            $clauses[] = $boolean . $clause;
        }
        
        return $sql . implode('', $clauses);
    }
}