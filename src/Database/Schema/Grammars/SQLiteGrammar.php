<?php

declare(strict_types=1);

namespace VelvetCMS\Database\Schema\Grammars;

use VelvetCMS\Database\Schema\Blueprint;
use VelvetCMS\Database\Schema\ForeignKeyDefinition;

class SQLiteGrammar extends Grammar
{
    protected array $modifiers = ['Nullable', 'Default', 'Incrementing'];

    public function compileCreate(Blueprint $blueprint): string
    {
        $columns = $this->getColumns($blueprint);

        foreach ($blueprint->getCommands() as $command) {
            if (!$command instanceof ForeignKeyDefinition) {
                continue;
            }
            
            $cols = $this->columnize($command->columns);
            $onTable = $this->wrap($command->onTable);
            $refs = $this->columnize($command->references);
            
            $sql = "FOREIGN KEY ({$cols}) REFERENCES {$onTable} ({$refs})";
            
            if ($command->onDelete) $sql .= " ON DELETE {$command->onDelete}";
            if ($command->onUpdate) $sql .= " ON UPDATE {$command->onUpdate}";
            
            $columns[] = $sql;
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command instanceof ForeignKeyDefinition) continue;

            $cols = $this->columnize($command['columns']);
            
            if ($command['type'] === 'primary') {
                $columns[] = "PRIMARY KEY ({$cols})";
            } elseif ($command['type'] === 'unique') {
                $columns[] = "UNIQUE ({$cols})";
            }
        }

        return 'CREATE TABLE ' . $this->wrap($blueprint->getTable()) . ' (' . implode(', ', $columns) . ')';
    }

    public function compileIndexes(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $command) {
            if ($command instanceof ForeignKeyDefinition) continue;
            
            if ($command['type'] === 'index') {
                $columns = $this->columnize($command['columns']);
                $table = $this->wrap($blueprint->getTable());
                $indexName = $this->wrap($command['name'] ?? $this->generateIndexName($blueprint->getTable(), $command['columns'], 'index'));
                
                $statements[] = "CREATE INDEX {$indexName} ON {$table} ({$columns})";
            }
        }

        return $statements;
    }

    public function compileForeign(Blueprint $blueprint): array
    {
        return [];
    }

    public function compileDrop(Blueprint $blueprint): string
    {
        return 'DROP TABLE ' . $this->wrap($blueprint->getTable());
    }

    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrap($blueprint->getTable());
    }

    protected function typeString(array $column): string
    {
        return 'VARCHAR';
    }

    protected function typeText(array $column): string
    {
        return 'TEXT';
    }

    protected function typeInteger(array $column): string
    {
        return 'INTEGER';
    }

    protected function typeBigInteger(array $column): string
    {
        return 'INTEGER';
    }

    protected function typeBoolean(array $column): string
    {
        return 'INTEGER';
    }

    protected function typeTimestamp(array $column): string
    {
        return 'DATETIME';
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
