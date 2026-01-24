<?php

declare(strict_types=1);

namespace VelvetCMS\Database\Schema\Grammars;

use VelvetCMS\Database\Schema\Blueprint;
use VelvetCMS\Database\Schema\ForeignKeyDefinition;

class PostgresGrammar extends Grammar
{
    protected array $modifiers = ['Nullable', 'Default'];

    public function compileCreate(Blueprint $blueprint): string
    {
        $columns = implode(', ', $this->getColumns($blueprint));
        return 'CREATE TABLE ' . $this->wrap($blueprint->getTable()) . " ($columns)";
    }

    public function compileDrop(Blueprint $blueprint): string
    {
        return 'DROP TABLE ' . $this->wrap($blueprint->getTable());
    }

    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrap($blueprint->getTable());
    }

    public function compileIndexes(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $command) {
            if ($command instanceof ForeignKeyDefinition) {
                continue;
            }

            if ($command['type'] === 'primary') {
                $columns = $this->columnize($command['columns']);
                $statements[] = 'ALTER TABLE ' . $this->wrap($blueprint->getTable()) . " ADD PRIMARY KEY ({$columns})";
                continue;
            }

            $columns = $this->columnize($command['columns']);
            $table = $this->wrap($blueprint->getTable());
            $indexName = $this->wrap($command['name'] ?? $this->generateIndexName($blueprint->getTable(), $command['columns'], $command['type']));
            
            if ($command['type'] === 'unique') {
                $statements[] = "CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columns})";
            } elseif ($command['type'] === 'index') {
                $statements[] = "CREATE INDEX {$indexName} ON {$table} ({$columns})";
            }
        }

        return $statements;
    }

    public function compileForeign(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $command) {
            if (!$command instanceof ForeignKeyDefinition) {
                continue;
            }

            $table = $this->wrap($blueprint->getTable());
            $columns = $this->columnize($command->columns);
            $onTable = $this->wrap($command->onTable);
            $references = $this->columnize($command->references);
            $name = $this->wrap($command->name ?? $this->generateIndexName($blueprint->getTable(), $command->columns, 'foreign'));

            $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$columns}) REFERENCES {$onTable} ({$references})";

            if ($command->onDelete) {
                $sql .= " ON DELETE {$command->onDelete}";
            }

            if ($command->onUpdate) {
                $sql .= " ON UPDATE {$command->onUpdate}";
            }

            $statements[] = $sql;
        }

        return $statements;
    }

    protected function typeString(array $column): string
    {
        return "VARCHAR({$column['length']})";
    }

    protected function typeText(array $column): string
    {
        return 'TEXT';
    }

    protected function typeInteger(array $column): string
    {
        if ($column['autoIncrement'] ?? false) {
            return 'SERIAL';
        }
        return 'INTEGER';
    }

    protected function typeBigInteger(array $column): string
    {
        if ($column['autoIncrement'] ?? false) {
            return 'BIGSERIAL';
        }
        return 'BIGINT';
    }

    protected function typeBoolean(array $column): string
    {
        return 'BOOLEAN';
    }

    protected function typeTimestamp(array $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    protected function modifyIncrementing(Blueprint $blueprint, array $column): string
    {
        if (in_array($column['type'], ['integer', 'bigInteger']) && ($column['autoIncrement'] ?? false)) {
            return ' PRIMARY KEY';
        }
        return '';
    }

    protected function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    protected function generateIndexName(string $table, array $columns, string $type): string
    {
        return strtolower($table . '_' . implode('_', $columns) . '_' . $type);
    }
}
