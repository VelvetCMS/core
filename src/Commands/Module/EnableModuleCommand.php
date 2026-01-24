<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Module;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\Application;
use VelvetCMS\Core\ModuleManager;

class EnableModuleCommand extends Command
{
    public static function category(): string
    {
        return 'Modules';
    }

    public function __construct(
        private readonly Application $app,
        private readonly ModuleManager $moduleManager
    ) {}

    public function signature(): string
    {
        return 'module:enable {module}';
    }

    public function description(): string
    {
        return 'Enable a module';
    }

    public function handle(): int
    {
        $moduleName = $this->argument(0);

        if (!$moduleName) {
            $this->error('Module name is required');
            $this->line('Usage: velvet module:enable <module>');
            return 1;
        }

        $discovered = $this->moduleManager->discover();

        if (!isset($discovered[$moduleName])) {
            $this->error("Module '{$moduleName}' not found");
            $this->line();
            $this->line('Available modules:');
            foreach (array_keys($discovered) as $availableModule) {
                $this->line("  - {$availableModule}");
            }
            return 1;
        }

        $statePath = $this->app->basePath() . '/storage/modules.json';

        $state = [];
        if (file_exists($statePath)) {
            $state = json_decode(file_get_contents($statePath), true) ?? [];
        }

        $enabled = $state['enabled'] ?? [];

        if (!in_array($moduleName, $enabled, true)) {
            $enabled[] = $moduleName;
            $state['enabled'] = $enabled;

            file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT));

            $this->line("\033[32m✓\033[0m Enabled module: {$moduleName}");
            $this->line("");
            
            $compiler = new CompileModuleCommand($this->app);
            return $compiler->handle();
        } else {
            $this->line("Module '{$moduleName}' is already enabled");
        }

        return 0;
    }
}
