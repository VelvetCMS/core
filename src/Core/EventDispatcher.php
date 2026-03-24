<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

class EventDispatcher
{
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;
    }

    public function dispatch(string $event, mixed $payload = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            $listener($payload);
        }
    }

    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) && $this->listeners[$event] !== [];
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    public function getEvents(): array
    {
        return array_keys($this->listeners);
    }
}
