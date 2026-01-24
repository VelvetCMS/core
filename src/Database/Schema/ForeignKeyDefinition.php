<?php

declare(strict_types=1);

namespace VelvetCMS\Database\Schema;

class ForeignKeyDefinition
{
    public array $columns;
    public ?string $name;
    public string $onTable;
    public array $references;
    public string $onDelete = 'RESTRICT';
    public string $onUpdate = 'RESTRICT';

    public function __construct(array $columns, ?string $name = null)
    {
        $this->columns = $columns;
        $this->name = $name;
    }

    public function references(string|array $columns): self
    {
        $this->references = (array) $columns;
        return $this;
    }

    public function on(string $table): self
    {
        $this->onTable = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }
}
