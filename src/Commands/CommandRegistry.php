<?php

declare(strict_types=1);

namespace VelvetCMS\Commands;

class CommandRegistry
{
    /** @var array<string, array{class: class-string<Command>, category?: string|null, hidden?: bool}> */
    private array $commands = [];
    
    public function register(string $name, string $commandClass, array $options = []): void
    {
        if (!is_subclass_of($commandClass, Command::class)) {
            throw new \InvalidArgumentException(
                "Command class must extend " . Command::class
            );
        }
        
        $this->commands[$name] = [
            'class' => $commandClass,
            'category' => $options['category'] ?? null,
            'hidden' => (bool) ($options['hidden'] ?? false),
        ];
    }
    
    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }
    
    /**
     * @return array{class: class-string<Command>, category?: string|null, hidden?: bool}|null
     */
    public function get(string $name): ?array
    {
        return $this->commands[$name] ?? null;
    }
    
    /**
     * @return array<string, array{class: class-string<Command>, category?: string|null, hidden?: bool}>
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * @return array<string, array<string, array{class: class-string<Command>, category?: string|null, hidden?: bool}>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->commands as $name => $meta) {
            if (!empty($meta['hidden'])) {
                continue;
            }

            $class = $meta['class'];
            $category = $meta['category'] ?? (is_callable([$class, 'category']) ? $class::category() : 'General');

            $groups[$category][$name] = $meta;
        }

        ksort($groups);

        foreach ($groups as &$commands) {
            ksort($commands);
        }

        return $groups;
    }
    
    public function run(string $name, array $arguments = [], array $options = []): int
    {
        $commandMeta = $this->get($name);

        if ($commandMeta === null) {
            echo "\033[31mCommand '{$name}' not found.\033[0m\n";
            echo "Run 'velvet list' to see available commands.\n";
            return 1;
        }
        
        $commandClass = $commandMeta['class'];
        
        if (in_array($commandClass, [
            \VelvetCMS\Commands\ListCommand::class,
            \VelvetCMS\Commands\HelpCommand::class,
        ], true)) {
            $command = new $commandClass($this);
        } else {
            global $app;
            
            if (isset($app) && method_exists($app, 'make')) {
                try {
                    $command = $app->make($commandClass);
                } catch (\Throwable $e) {
                    $command = $this->makeCommand($commandClass, $app);
                }
            } else {
                $command = new $commandClass();
            }
        }
        
        $command->setArguments($arguments);
        $command->setOptions($options);
        
        try {
            return $command->handle();
        } catch (\Throwable $e) {
            echo "\033[31m[ERROR]\033[0m {$e->getMessage()}\n";
            
            if (config('app.debug', false)) {
                echo "\n{$e->getTraceAsString()}\n";
            }
            
            return 1;
        }
    }
    
    private function makeCommand(string $commandClass, $app): object
    {
        $reflection = new \ReflectionClass($commandClass);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return new $commandClass();
        }
        
        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            
            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                try {
                    $dependencies[] = $app->make($typeName);
                } catch (\Throwable $e) {
                    if ($param->isDefaultValueAvailable()) {
                        $dependencies[] = $param->getDefaultValue();
                    } else {
                        throw $e;
                    }
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            }
        }
        
        return new $commandClass(...$dependencies);
    }
    
    public function parseArgv(array $argv): array
    {
        array_shift($argv);
        
        $commandName = $argv[0] ?? null;
        $arguments = [];
        $options = [];
        
        if (!$commandName || str_starts_with($commandName, '-')) {
            return [null, $arguments, $options];
        }
        
        array_shift($argv);
        
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            } elseif (str_starts_with($arg, '-')) {
                $flag = substr($arg, 1);
                $options[$flag] = true;
            } else {
                $arguments[] = $arg;
            }
        }
        
        return [$commandName, $arguments, $options];
    }
}
