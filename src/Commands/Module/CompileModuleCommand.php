<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Module;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\Application;
use VelvetCMS\Core\ModuleManager;
use VelvetCMS\Core\ModuleManifest;
use VelvetCMS\Core\VersionRegistry;

class CompileModuleCommand extends Command
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
        return 'module:compile';
    }

    public function description(): string
    {
        return 'Compile module manifest (validate, resolve load order and dependencies)';
    }

    public function handle(): int
    {
        $this->line('Compiling modules...');
        $this->line();

        $moduleManager = $this->app->make(ModuleManager::class);
        $versionRegistry = VersionRegistry::instance();

        $discovered = $moduleManager->discover();

        if (empty($discovered)) {
            $this->line('No modules discovered.');
            return 0;
        }

        $this->line("Discovered \033[32m" . count($discovered) . "\033[0m modules");
        $this->line();

        $statePath = $this->app->basePath() . '/storage/modules.json';
        $state = [];

        if (file_exists($statePath)) {
            $state = json_decode(file_get_contents($statePath), true) ?? [];
        }

        $enabledModules = $state['enabled'] ?? [];

        $validated = [];
        $errors = [];

        foreach ($discovered as $name => $manifest) {
            $enabled = in_array($name, $enabledModules, true);

            if (!$enabled) {
                $this->line("  \033[90m[SKIP]\033[0m {$name} (disabled)");
                continue;
            }

            $issues = $moduleManager->validate($name, $manifest, $discovered);

            if (!empty($issues)) {
                $errors[$name] = $issues;
                $this->line("  \033[31m[FAIL]\033[0m {$name}");
                foreach ($issues as $issue) {
                    $this->line("    ⚠️  {$issue}");
                }
            } else {
                $validated[$name] = $manifest;
                $this->line("  \033[32m[OK]\033[0m {$name} @{$manifest['version']}");
            }
        }

        $this->line();

        if (!empty($errors)) {
            $this->line("\033[31m✗ Compilation failed with " . count($errors) . " error(s)\033[0m");
            return 1;
        }

        try {
            $loadOrder = $moduleManager->resolveLoadOrder($validated);
            $this->line('Resolved load order: ' . implode(' → ', $loadOrder));
            $this->line();
        } catch (\Exception $e) {
            $this->line("\033[31m✗ Failed to resolve load order: {$e->getMessage()}\033[0m");
            return 1;
        }

        $compiled = [
            'timestamp' => date('c'),
            'version' => $versionRegistry->getVersion('core'),
            'modules' => [],
        ];

        foreach ($loadOrder as $index => $name) {
            $manifest = $validated[$name];

            $typed = ModuleManifest::fromArray($name, $manifest, true);

            $compiled['modules'][] = [
                'name' => $typed->name,
                'version' => $typed->version,
                'path' => $typed->path,
                'entry' => $typed->entry,
                'enabled' => true,
                'load_order' => $index + 1,
                'requires' => $typed->requires,
                'conflicts' => $typed->conflicts,
                'provides' => $typed->provides,
                'description' => $typed->description,
                'stability' => $typed->stability,
                'extra' => $typed->extra,
            ];
        }

        $compiledPath = $this->app->basePath() . '/storage/modules-compiled.json';
        file_put_contents($compiledPath, json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->generateAutoloader($compiled['modules']);

        try {
            $this->verifyEntryAutoload($compiled['modules']);
        } catch (\RuntimeException $e) {
            $this->line("\033[31m✗ Autoload verification failed: {$e->getMessage()}\033[0m");
            return 1;
        }

        $this->line("\033[32m✓ Successfully compiled " . count($compiled['modules']) . " module(s)\033[0m");
        $this->line('  Written to: storage/modules-compiled.json');
        $this->line('  Autoloader: storage/modules-autoload.php');

        return 0;
    }

    private function generateAutoloader(array $modules): void
    {
        $autoloadMappings = [];
        $files = [];

        foreach ($modules as $moduleData) {
            $vendorAutoload = $moduleData['path'] . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                $files[] = $vendorAutoload;
            }

            $composerPath = $moduleData['path'] . '/composer.json';

            if (!file_exists($composerPath)) {
                continue;
            }

            $composer = json_decode(file_get_contents($composerPath), true);

            if (!isset($composer['autoload']['psr-4'])) {
                continue;
            }

            foreach ($composer['autoload']['psr-4'] as $namespace => $path) {
                $fullPath = $moduleData['path'] . '/' . rtrim($path, '/');
                $autoloadMappings[$namespace] = $fullPath;
            }
        }

        $config = [
            'psr-4' => $autoloadMappings,
            'files' => $files,
        ];

        $php = "<?php\n\n";
        $php .= "/**\n";
        $php .= " * Auto-generated module autoloader\n";
        $php .= ' * Generated: ' . date('Y-m-d H:i:s') . "\n";
        $php .= " * Do not edit manually - run 'php velvet module:compile' to regenerate\n";
        $php .= " */\n\n";
        $php .= 'return ' . var_export($config, true) . ";\n";

        $autoloadPath = $this->app->basePath() . '/storage/modules-autoload.php';
        file_put_contents($autoloadPath, $php);

        clearstatcache(true, $autoloadPath);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($autoloadPath, true);
        }
    }

    private function verifyEntryAutoload(array $modules): void
    {
        $autoloadPath = $this->app->basePath() . '/storage/modules-autoload.php';

        if (!file_exists($autoloadPath)) {
            throw new \RuntimeException('module autoloader not generated');
        }

        clearstatcache(true, $autoloadPath);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($autoloadPath, true);
        }

        $config = require $autoloadPath;
        $mappings = $config['psr-4'] ?? $config;

        if (!is_array($mappings)) {
            throw new \RuntimeException('invalid module autoloader structure');
        }

        foreach ($mappings as $namespace => $path) {
            spl_autoload_register(static function ($class) use ($namespace, $path): void {
                if (!str_starts_with($class, $namespace)) {
                    return;
                }

                $relativeClass = substr($class, strlen($namespace));
                $file = $path . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                }
            });
        }

        $missing = [];

        foreach ($modules as $moduleData) {
            $entry = $moduleData['entry'] ?? null;

            if ($entry === null || $entry === '') {
                continue;
            }

            if (!class_exists($entry)) {
                $name = $moduleData['name'] ?? 'unknown';
                $missing[] = sprintf('%s (%s)', $name, $entry);
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException('unable to autoload entry class for: ' . implode(', ', $missing));
        }
    }
}
