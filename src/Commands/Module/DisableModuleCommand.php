<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Module;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\Application;

class DisableModuleCommand extends Command
{
    public static function category(): string
    {
        return 'Modules';
    }

    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function signature(): string
    {
        return 'module:disable {module}';
    }

    public function description(): string
    {
        return 'Disable a module';
    }

    public function handle(): int
    {
        $moduleName = $this->argument(0);

        if (!$moduleName) {
            $this->error('Module name is required');
            $this->line('Usage: velvet module:disable <module>');
            return 1;
        }

        $statePath = $this->app->basePath() . '/storage/modules.json';

        $state = [];
        if (file_exists($statePath)) {
            $state = json_decode(file_get_contents($statePath), true) ?? [];
        }

        $enabled = $state['enabled'] ?? [];

        if (!in_array($moduleName, $enabled, true)) {
            $this->line("Module '{$moduleName}' is not currently enabled");
            return 0;
        }

        $key = array_search($moduleName, $enabled, true);
        if ($key !== false) {
            unset($enabled[$key]);
            $state['enabled'] = array_values($enabled);

            file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT));

            $this->line("\033[32m✓\033[0m Disabled module: {$moduleName}");
            $this->line('');

            $compiler = new CompileModuleCommand($this->app);
            return $compiler->handle();
        }

        return 0;
    }
}
