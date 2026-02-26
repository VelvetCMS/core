<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Module;

use VelvetCMS\Commands\Command;
use VelvetCMS\Commands\Concerns\InteractsWithTenancy;
use VelvetCMS\Core\Application;
use VelvetCMS\Core\ModuleManager;
use VelvetCMS\Core\Tenancy\ModuleArtifactPaths;

class ListModuleCommand extends Command
{
    use InteractsWithTenancy;

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
        return 'module:list [--tenant=] [--all-tenants]';
    }

    public function description(): string
    {
        return 'List all discovered modules';
    }

    public function handle(): int
    {
        if ((bool) $this->option('all-tenants', false)) {
            return $this->handleAllTenants();
        }

        $moduleManager = $this->app->make(ModuleManager::class);
        $discovered = $moduleManager->discover();

        if (empty($discovered)) {
            $this->line('No modules discovered.');
            return 0;
        }

        $statePath = $this->firstExisting(ModuleArtifactPaths::stateCandidates($this->app->basePath()))
            ?? ModuleArtifactPaths::statePath(basePath: $this->app->basePath());
        $enabledModules = [];
        if (file_exists($statePath)) {
            $state = json_decode(file_get_contents($statePath), true);
            $enabledModules = $state['enabled'] ?? [];
        }

        $compiledPath = $this->firstExisting(ModuleArtifactPaths::compiledCandidates($this->app->basePath()))
            ?? ModuleArtifactPaths::compiledPath(basePath: $this->app->basePath());
        $compiledModules = [];
        if (file_exists($compiledPath)) {
            $compiledData = json_decode(file_get_contents($compiledPath), true);
            foreach ($compiledData['modules'] ?? [] as $m) {
                $compiledModules[$m['name']] = true;
            }
        }

        $this->line("\033[1mModules\033[0m");
        $this->line(sprintf('  %-20s %-15s %-15s %s', 'Name', 'Version', 'Status', 'Path'));
        $this->line('  ' . str_repeat('-', 80));

        foreach ($discovered as $name => $manifest) {
            $version = $manifest['version'] ?? 'unknown';
            $isEnabled = in_array($name, $enabledModules, true);
            $isCompiled = isset($compiledModules[$name]);

            if ($isEnabled) {
                if ($isCompiled) {
                    $status = "\033[32mEnabled\033[0m";
                } else {
                    $status = "\033[33mEnabled (Pending)\033[0m";
                }
            } else {
                $status = "\033[90mDisabled\033[0m";
            }

            $path = $manifest['path'];
            if (str_starts_with($path, $this->app->basePath())) {
                $path = '.' . substr($path, strlen($this->app->basePath()));
            }

            $this->line(sprintf(
                '  %-20s %-15s %-25s %s',
                $name,
                $version,
                $status,
                "\033[90m{$path}\033[0m"
            ));
        }

        $this->line();
        return 0;
    }

    private function handleAllTenants(): int
    {
        try {
            $tenants = $this->resolveTenantSelection(allowAllTenants: true, fallbackToCurrentTenant: false);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($tenants === []) {
            $this->warning('No tenants discovered under user tenancy root.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenantId) {
            $this->line();
            $this->line("\033[1m[tenant: {$tenantId}]\033[0m");

            $exitCode = $this->runVelvetSubcommand($this->app->basePath(), 'module:list', $tenantId);
            if ($exitCode !== 0) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $paths
     */
    private function firstExisting(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
