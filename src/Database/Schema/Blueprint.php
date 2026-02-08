<?php

declare(strict_types=1);

namespace VelvetCMS\Database\Schema;

class Blueprint
{
    private string $table;
    private array $columns = [];
    private array $commands = [];
    private bool $creating = false;
    private bool $dropping = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function create(): void
    {
        $this->creating = true;
    }

    public function drop(): void
    {
        $this->dropping = true;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function creating(): bool
    {
        return $this->creating;
    }

    public function dropping(): bool
    {
        return $this->dropping;
    }

    public function id(string $column = 'id'): self
    {
        return $this->bigInteger($column, true, true);
    }

    public function string(string $column, int $length = 255): self
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    public function text(string $column): self
    {
        return $this->addColumn('text', $column);
    }

    public function longText(string $column): self
    {
        return $this->addColumn('longText', $column);
    }

    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function json(string $column): self
    {
        return $this->addColumn('json', $column);
    }

    public function boolean(string $column): self
    {
        return $this->addColumn('boolean', $column);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function timestamp(string $column): self
    {
        return $this->addColumn('timestamp', $column);
    }

    public function nullable(bool $value = true): self
    {
        $this->columns[array_key_last($this->columns)]['nullable'] = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->columns[array_key_last($this->columns)]['default'] = $value;
        return $this;
    }

    public function useCurrent(): self
    {
        $this->columns[array_key_last($this->columns)]['useCurrent'] = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->columns[array_key_last($this->columns)]['unsigned'] = true;
        return $this;
    }

    public function index(string|array|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $lastColumn = $this->columns[array_key_last($this->columns)]['name'];
            $columns = [$lastColumn];
        } else {
            $columns = (array) $columns;
        }
        return $this->addCommand('index', compact('columns', 'name'));
    }

    public function unique(string|array|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $lastColumn = $this->columns[array_key_last($this->columns)]['name'];
            $columns = [$lastColumn];
        } else {
            $columns = (array) $columns;
        }
        return $this->addCommand('unique', compact('columns', 'name'));
    }

    public function primary(string|array|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $lastColumn = $this->columns[array_key_last($this->columns)]['name'];
            $columns = [$lastColumn];
        } else {
            $columns = (array) $columns;
        }
        return $this->addCommand('primary', compact('columns', 'name'));
    }

    public function foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition
    {
        $command = new ForeignKeyDefinition((array) $columns, $name);
        $this->commands[] = $command;
        return $command;
    }

    private function addCommand(string $type, array $parameters = []): self
    {
        $this->commands[] = array_merge(compact('type'), $parameters);
        return $this;
    }

    private function addColumn(string $type, string $name, array $parameters = []): self
    {
        $this->columns[] = array_merge(compact('type', 'name'), $parameters, [
            'nullable' => false
        ]);
        return $this;
    }
}
