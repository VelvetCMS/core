<?php

declare(strict_types=1);

namespace VelvetCMS\Database\Schema\Grammars;

use VelvetCMS\Database\Schema\Blueprint;

abstract class Grammar
{
    protected array $modifiers = [];

    abstract public function compileCreate(Blueprint $blueprint): string;
    abstract public function compileDrop(Blueprint $blueprint): string;
    abstract public function compileDropIfExists(Blueprint $blueprint): string;
    abstract public function compileIndexes(Blueprint $blueprint): array;
    abstract public function compileForeign(Blueprint $blueprint): array;
    abstract protected function wrap(string $value): string;

    abstract protected function typeString(array $column): string;
    abstract protected function typeText(array $column): string;
    abstract protected function typeLongText(array $column): string;
    abstract protected function typeInteger(array $column): string;
    abstract protected function typeBigInteger(array $column): string;
    abstract protected function typeBoolean(array $column): string;
    abstract protected function typeTimestamp(array $column): string;
    abstract protected function typeJson(array $column): string;

    public function compile(Blueprint $blueprint): array
    {
        if ($blueprint->creating()) {
            return array_merge(
                [$this->compileCreate($blueprint)],
                $this->compileIndexes($blueprint),
                $this->compileForeign($blueprint)
            );
        }

        if ($blueprint->dropping()) {
            return [$this->compileDrop($blueprint)];
        }

        return [];
    }

    protected function getColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $sql = $this->wrap($column['name']) . ' ' . $this->getType($column);
            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    protected function getType(array $column): string
    {
        $method = 'type' . ucfirst($column['type']);
        return $this->{$method}($column);
    }

    protected function addModifiers(string $sql, Blueprint $blueprint, array $column): string
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $sql .= $this->{$method}($blueprint, $column);
            }
        }

        return $sql;
    }

    protected function modifyDefault(Blueprint $blueprint, array $column): string
    {
        if (!empty($column['useCurrent'])) {
            return ' DEFAULT CURRENT_TIMESTAMP';
        }

        if (!isset($column['default'])) {
            return '';
        }

        return ' DEFAULT ' . $this->quoteString($column['default']);
    }

    protected function quoteString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
