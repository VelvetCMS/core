<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Module;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\Application;
use VelvetCMS\Core\ModuleManager;

class InfoModuleCommand extends Command
{
    public static function category(): string
    {
        return 'Modules';
    }

    public function __construct(
        private readonly Application $app
    ) {
    }

    public function signature(): string
    {
        return 'module:info {name}';
    }

    public function description(): string
    {
        return 'Show detailed information about a module';
    }

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Module name is required.');
            return 1;
        }

        $moduleManager = $this->app->make(ModuleManager::class);
        $module = $moduleManager->get($name);

        if ($module === null) {
            $this->error("Module '{$name}' is not loaded.");
            return 1;
        }

        $manifest = $module->manifestObject();

        $this->line("\033[1m{$manifest->name}\033[0m \033[33m{$manifest->version}\033[0m");
        if ($manifest->description) {
            $this->line("  {$manifest->description}");
        }
        $this->line();

        $path = $manifest->path;
        if (!str_starts_with($path, '/')) {
            $path = $this->app->basePath() . '/' . $path;
        }

        $this->line("\033[1mPath\033[0m");
        $this->line("  {$path}");
        $this->line();

        $this->line("\033[1mEntry\033[0m");
        $this->line("  {$manifest->entry}");
        $this->line();

        if ($manifest->requires !== []) {
            $this->line("\033[1mRequires\033[0m");
            foreach ($manifest->requires as $dep => $constraint) {
                $this->line("  {$dep}: {$constraint}");
            }
            $this->line();
        }

        $this->printConfigInfo($name, $path);
        $this->printViewsInfo($path);
        $this->printRoutesInfo($path, $manifest->extra);
        $this->printCommandsInfo($manifest->commands);

        return 0;
    }

    private function printConfigInfo(string $namespace, string $path): void
    {
        $configDir = $path . '/config';
        if (!is_dir($configDir)) {
            return;
        }

        $files = glob($configDir . '/*.php');
        if ($files === [] || $files === false) {
            return;
        }

        $this->line("\033[1mConfig\033[0m");
        foreach ($files as $file) {
            $fileName = basename($file, '.php');
            $this->line("  {$namespace}:{$fileName}");
        }
        $this->line();
    }

    private function printViewsInfo(string $path): void
    {
        $viewsDir = $path . '/resources/views';
        if (!is_dir($viewsDir)) {
            return;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        $this->line("\033[1mViews\033[0m");
        $this->line("  {$viewsDir}");
        $this->line("  {$count} template(s)");
        $this->line();
    }

    private function printRoutesInfo(string $path, array $extra): void
    {
        $routesDir = $path . '/routes';
        if (!is_dir($routesDir)) {
            return;
        }

        $files = glob($routesDir . '/*.php');
        if ($files === [] || $files === false) {
            return;
        }

        $autoload = ($extra['autoload']['routes'] ?? true) !== false;

        $this->line("\033[1mRoutes\033[0m");
        foreach ($files as $file) {
            $this->line('  ' . basename($file));
        }
        $this->line('  Auto-load: ' . ($autoload ? "\033[32myes\033[0m" : "\033[33mno\033[0m (manual)"));
        $this->line();
    }

    private function printCommandsInfo(array $commands): void
    {
        if ($commands === []) {
            return;
        }

        $this->line("\033[1mCommands\033[0m");
        foreach ($commands as $signature => $class) {
            $this->line("  \033[32m{$signature}\033[0m → {$class}");
        }
        $this->line();
    }
}
