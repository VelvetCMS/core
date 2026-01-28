<?php

declare(strict_types=1);

namespace VelvetCMS\Scheduling;

class Schedule
{
    protected array $tasks = [];

    public function call(callable $callback, array $parameters = []): Task
    {
        $task = new Task($callback, $parameters);
        $this->tasks[] = $task;
        return $task;
    }

    public function command(string $command, array $parameters = []): Task
    {
        $task = Task::command($command, $parameters);
        $this->tasks[] = $task;
        return $task;
    }

    public function getDueTasks(): array
    {
        return array_filter($this->tasks, fn (Task $task) => $task->isDue());
    }

    public function getAllTasks(): array
    {
        return $this->tasks;
    }
}
