<?php

declare(strict_types=1);

namespace VelvetCMS\Database;

use ArrayAccess;
use Countable;
use Iterator;

class Collection implements ArrayAccess, Countable, Iterator
{
    private array $items;
    private int $position = 0;
    
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }
    
    public function all(): array
    {
        return $this->items;
    }
    
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }
    
    public function last(): mixed
    {
        return empty($this->items) ? null : end($this->items);
    }
    
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }
    
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback));
    }
    
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }
    
    public function pluck(string $column): self
    {
        return new self(array_column($this->items, $column));
    }
    
    public function isEmpty(): bool
    {
        return empty($this->items);
    }
    
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }
    
    public function get(int $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }
    
    public function take(int $count): self
    {
        return new self(array_slice($this->items, 0, $count));
    }
    
    public function sort(?callable $callback = null): self
    {
        $items = $this->items;
        
        if ($callback) {
            usort($items, $callback);
        } else {
            sort($items);
        }
        
        return new self($items);
    }
    
    public function reverse(): self
    {
        return new self(array_reverse($this->items));
    }
    
    public function chunk(int $size): self
    {
        $chunks = [];
        foreach (array_chunk($this->items, $size) as $chunk) {
            $chunks[] = new self($chunk);
        }
        return new self($chunks);
    }
    
    public function groupBy(string|callable $groupBy): self
    {
        $groups = [];
        
        foreach ($this->items as $item) {
            $key = is_callable($groupBy) 
                ? $groupBy($item) 
                : (is_array($item) ? ($item[$groupBy] ?? null) : $item->$groupBy ?? null);
                
            if ($key !== null) {
                if (!isset($groups[$key])) {
                    $groups[$key] = [];
                }
                $groups[$key][] = $item;
            }
        }
        
        return new self(array_map(fn($group) => new self($group), $groups));
    }

    public function keyBy(string|callable $keyBy): array
    {
        $keyed = [];
        
        foreach ($this->items as $item) {
            $key = is_callable($keyBy) 
                ? $keyBy($item) 
                : (is_array($item) ? ($item[$keyBy] ?? null) : $item->$keyBy ?? null);
            
            if ($key !== null) {
                $keyed[$key] = $item;
            }
        }
        
        return $keyed;
    }
    
    public function unique(?string $key = null): self
    {
        if ($key === null) {
            return new self(array_values(array_unique($this->items, SORT_REGULAR)));
        }
        
        $unique = [];
        $seen = [];
        
        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$key] ?? null) : $item->$key ?? null;
            
            if (!in_array($value, $seen, true)) {
                $seen[] = $value;
                $unique[] = $item;
            }
        }
        
        return new self($unique);
    }
    
    public function values(): self
    {
        return new self(array_values($this->items));
    }
    
    public function sortBy(string|callable $sortBy, bool $descending = false): self
    {
        $items = $this->items;
        
        usort($items, function($a, $b) use ($sortBy, $descending) {
            $aValue = is_callable($sortBy) 
                ? $sortBy($a) 
                : (is_array($a) ? ($a[$sortBy] ?? null) : $a->$sortBy ?? null);
                
            $bValue = is_callable($sortBy) 
                ? $sortBy($b) 
                : (is_array($b) ? ($b[$sortBy] ?? null) : $b->$sortBy ?? null);
            
            $result = $aValue <=> $bValue;
            return $descending ? -$result : $result;
        });
        
        return new self($items);
    }
    
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        
        return $this;
    }
    
    public function contains(mixed $value, ?string $key = null): bool
    {
        if ($key === null) {
            return in_array($value, $this->items, true);
        }
        
        foreach ($this->items as $item) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : $item->$key ?? null;
            if ($itemValue === $value) {
                return true;
            }
        }
        
        return false;
    }
    
    public function sum(?string $key = null): int|float
    {
        if ($key === null) {
            return array_sum($this->items);
        }
        
        return array_sum(array_map(
            fn($item) => is_array($item) ? ($item[$key] ?? 0) : $item->$key ?? 0,
            $this->items
        ));
    }
    
    public function avg(?string $key = null): int|float
    {
        $count = $this->count();
        return $count > 0 ? $this->sum($key) / $count : 0;
    }
    
    public function merge(self|array $items): self
    {
        $mergeItems = $items instanceof self ? $items->all() : $items;
        return new self(array_merge($this->items, $mergeItems));
    }
    
    public function toJson(): string
    {
        return json_encode($this->items, JSON_THROW_ON_ERROR);
    }
    
    public function count(): int
    {
        return count($this->items);
    }
    
    public function current(): mixed
    {
        return $this->items[$this->position];
    }
    
    public function key(): int
    {
        return $this->position;
    }
    
    public function next(): void
    {
        ++$this->position;
    }
    
    public function rewind(): void
    {
        $this->position = 0;
    }
    
    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }
    
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }
    
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }
    
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }
    
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}