<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Module;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\ModuleManager;
use VelvetCMS\Core\Application;

class ListModuleCommand extends Command
{
    public static function category(): string
    {
        return 'Modules';
    }

    public function __construct(
        private readonly Application $app
    ) {}

    public function signature(): string
    {
        return 'module:list';
    }

    public function description(): string
    {
        return 'List all discovered modules';
    }

    public function handle(): int
    {
        $moduleManager = $this->app->make(ModuleManager::class);
        $discovered = $moduleManager->discover();

        if (empty($discovered)) {
            $this->line('No modules discovered.');
            return 0;
        }

        $statePath = $this->app->basePath() . '/storage/modules.json';
        $enabledModules = [];
        if (file_exists($statePath)) {
            $state = json_decode(file_get_contents($statePath), true);
            $enabledModules = $state['enabled'] ?? [];
        }

        $compiledPath = $this->app->basePath() . '/storage/modules-compiled.json';
        $compiledModules = [];
        if (file_exists($compiledPath)) {
            $compiledData = json_decode(file_get_contents($compiledPath), true);
            foreach ($compiledData['modules'] ?? [] as $m) {
                $compiledModules[$m['name']] = true;
            }
        }

        $this->line("\033[1mModules\033[0m");
        $this->line(sprintf("  %-20s %-15s %-15s %s", "Name", "Version", "Status", "Path"));
        $this->line("  " . str_repeat('-', 80));

        foreach ($discovered as $name => $manifest) {
            $version = $manifest['version'] ?? 'unknown';
            $isEnabled = in_array($name, $enabledModules, true);
            $isCompiled = isset($compiledModules[$name]);

            if ($isEnabled) {
                if ($isCompiled) {
                    $status = "\033[32mEnable\033[0m";
                } else {
                    $status = "\033[33mEnable (Pending)\033[0m";
                }
            } else {
                $status = "\033[90mDisabled\033[0m";
            }

            $path = $manifest['path'];
            if (str_starts_with($path, $this->app->basePath())) {
                $path = '.' . substr($path, strlen($this->app->basePath()));
            }

            $this->line(sprintf("  %-20s %-15s %-25s %s", 
                $name, 
                $version, 
                $status,
                "\033[90m{$path}\033[0m"
            ));
        }

        $this->line();
        return 0;
    }
}
