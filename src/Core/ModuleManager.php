<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

use VelvetCMS\Contracts\Module;
use VelvetCMS\Exceptions\ModuleException;

class ModuleManager
{
    private Application $app;
    private VersionRegistry $versionRegistry;
    private string $basePath;

    /** @var array<string, Module> */
    private array $modules = [];

    /** @var array<string, ModuleManifest> */
    private array $manifests = [];

    private bool $booted = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->versionRegistry = VersionRegistry::instance();
        $this->basePath = $app->basePath();
    }

    public function load(): self
    {
        $compiledPath = $this->basePath . '/storage/modules-compiled.json';

        if (!file_exists($compiledPath)) {
            // No compiled manifest, skip module loading
            return $this;
        }

        $compiled = json_decode(file_get_contents($compiledPath), true);

        if (!is_array($compiled) || !isset($compiled['modules'])) {
            return $this;
        }

        // Register autoloader for all modules (optimized)
        $this->registerAutoloader();

        // Load modules in pre-determined order
        foreach ($compiled['modules'] as $moduleData) {
            if (!is_array($moduleData)) {
                continue;
            }

            if (!($moduleData['enabled'] ?? false)) {
                continue;
            }

            $name = (string) ($moduleData['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $manifest = ModuleManifest::fromArray($name, $moduleData, true);
            $this->loadModule($manifest);
        }

        return $this;
    }

    private function loadModule(ModuleManifest $manifest): void
    {
        $name = $manifest->name;
        $path = $manifest->path;
        $entryClass = $manifest->entry;

        // Resolve absolute path
        if (!str_starts_with($path, '/')) {
            $path = $this->basePath . '/' . $path;
        }

        if (!class_exists($entryClass)) {
            throw new ModuleException("Module '{$name}' entry class not found: {$entryClass}");
        }

        $module = new $entryClass($path, $manifest);

        if (!$module instanceof Module) {
            throw new ModuleException("Module '{$name}' must implement " . Module::class);
        }

        // Register module's public assets if available
        $this->registerModuleAssets($module);

        $this->modules[$name] = $module;
        $this->manifests[$name] = $manifest;
    }

    private function registerModuleAssets(Module $module): void
    {
        if (!method_exists($module, 'publicPath') || !method_exists($module, 'assetsPrefix')) {
            return;
        }

        $publicPath = $module->publicPath();
        $prefix = $module->assetsPrefix();

        if ($publicPath && $prefix) {
            \VelvetCMS\Http\AssetServer::module($prefix, $publicPath);
        }
    }

    public function registerAutoloader(): void
    {
        $autoloadPath = $this->basePath . '/storage/modules-autoload.php';

        if (!file_exists($autoloadPath)) {
            // Fallback: no compiled autoloader, autoload will fail gracefully
            return;
        }

        $mappings = require $autoloadPath;

        // Strict format: require psr-4 key
        if (!isset($mappings['psr-4'])) {
            return;
        }

        $psr4 = $mappings['psr-4'];
        $files = $mappings['files'] ?? [];

        // Load module-specific autoloaders (e.g. vendor/autoload.php)
        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }

        spl_autoload_register(function ($class) use ($psr4) {
            // Check each namespace mapping
            foreach ($psr4 as $namespace => $path) {
                // Check if class is in this namespace
                if (strpos($class, $namespace) !== 0) {
                    continue;
                }

                // Get relative class name
                $relativeClass = substr($class, strlen($namespace));
                $file = $path . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }

    public function register(): self
    {
        foreach ($this->modules as $module) {
            $module->register($this->app);
        }

        return $this;
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }

        $this->booted = true;

        return $this;
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $name): ?Module
    {
        return $this->modules[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /** @return array<string, array> */
    public function discover(): array
    {
        $discovered = [];

        // 1. From config
        $configModules = $this->discoverFromConfig();
        $discovered = array_merge($discovered, $configModules);

        // 2. From filesystem
        $fsModules = $this->discoverFromFilesystem();
        $discovered = array_merge($discovered, $fsModules);

        // 3. From composer
        $composerModules = $this->discoverFromComposer();
        $discovered = array_merge($discovered, $composerModules);

        return $discovered;
    }

    private function discoverFromConfig(): array
    {
        $modules = [];
        $config = config('modules', []);

        foreach ($config['modules'] ?? [] as $name => $moduleConfig) {
            if (is_string($moduleConfig)) {
                $moduleConfig = ['path' => $moduleConfig];
            }

            $manifestPath = $this->resolveModulePath($moduleConfig['path'] ?? $name) . '/module.json';

            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if (is_array($manifest)) {
                    $modules[$name] = array_merge($manifest, [
                        'path' => $moduleConfig['path'] ?? $name,
                        'source' => 'config',
                    ]);
                }
            }
        }

        return $modules;
    }

    private function discoverFromFilesystem(): array
    {
        $modules = [];
        $config = config('modules', []);
        $paths = $config['paths'] ?? [];
        $tenantPaths = $this->getTenantModulePaths($config);
        if (!empty($tenantPaths)) {
            $paths = array_merge($paths, $tenantPaths);
        }
        $paths = array_values(array_unique(array_filter($paths, 'is_string')));

        foreach ($paths as $path) {
            $resolvedPath = $this->resolveModulePath($path);

            // Support glob patterns
            if (str_contains($path, '*')) {
                $matchedPaths = glob($resolvedPath);
                foreach ($matchedPaths as $matchedPath) {
                    if (is_dir($matchedPath)) {
                        $matchedPath = realpath($matchedPath);
                        $module = $this->loadManifestFromPath($matchedPath);
                        if ($module) {
                            $modules[$module['name']] = array_merge($module, [
                                'path' => $matchedPath,
                                'source' => 'filesystem',
                            ]);
                        }
                    }
                }
            } else {
                if (is_dir($resolvedPath)) {
                    $module = $this->loadManifestFromPath($resolvedPath);
                    if ($module) {
                        $modules[$module['name']] = array_merge($module, [
                            'path' => $resolvedPath,
                            'source' => 'filesystem',
                        ]);
                    }
                }
            }
        }

        return $modules;
    }

    /** @return string[] */
    private function getTenantModulePaths(array $config): array
    {
        if (!\VelvetCMS\Core\Tenancy\TenancyManager::isEnabled()) {
            return [];
        }

        $tenantId = \VelvetCMS\Core\Tenancy\TenancyManager::currentId();
        if (!is_string($tenantId) || $tenantId === '') {
            return [];
        }

        $paths = $config['tenant_paths'] ?? [];
        if (is_string($paths)) {
            $paths = [$paths];
        }

        $resolved = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $resolved[] = str_replace('{tenant}', $tenantId, $path);
        }

        return $resolved;
    }

    private function discoverFromComposer(): array
    {
        $modules = [];
        $installedPath = $this->basePath . '/vendor/composer/installed.json';

        if (!file_exists($installedPath)) {
            return $modules;
        }

        $installed = json_decode(file_get_contents($installedPath), true);
        $packages = $installed['packages'] ?? [];

        foreach ($packages as $package) {
            if (($package['type'] ?? '') === 'velvetcms-module') {
                $packagePath = $this->basePath . '/vendor/' . $package['name'];
                $module = $this->loadManifestFromPath($packagePath);

                if ($module) {
                    $modules[$module['name']] = array_merge($module, [
                        'path' => $packagePath,
                        'source' => 'composer',
                        'package' => $package['name'],
                    ]);
                }
            }
        }

        return $modules;
    }

    private function loadManifestFromPath(string $path): ?array
    {
        $manifestPath = $path . '/module.json';

        if (!file_exists($manifestPath)) {
            return null;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (!is_array($manifest) || !isset($manifest['name'])) {
            return null;
        }

        return $manifest;
    }

    private function resolveModulePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->basePath . '/' . $path;
    }

    /** @return array Validation issues */
    public function validate(string $name, array $manifest, array $availableManifests = []): array
    {
        $issues = [];

        // Check if entry class exists
        $entryClass = $manifest['entry'] ?? null;
        if (!$entryClass) {
            $issues[] = "Module '{$name}' missing 'entry' in manifest";
        }

        if ($availableManifests === []) {
            // $this->manifests contains ModuleManifest objects for already-loaded modules.
            $loaded = [];
            foreach ($this->manifests as $loadedName => $loadedManifest) {
                $loaded[$loadedName] = $loadedManifest->toArray();
            }
            $availableManifests = array_merge($loaded, $this->discover());
        }

        // Check version requirements
        $requires = $manifest['requires'] ?? [];

        foreach ($requires as $dependency => $constraint) {
            if ($dependency === 'core') {
                if (!$this->versionRegistry->satisfies($this->versionRegistry->getVersion('core'), $constraint)) {
                    $issues[] = "Module '{$name}' requires core {$constraint}, but current is " . $this->versionRegistry->getVersion('core');
                }
            } elseif ($dependency === 'php') {
                if (!$this->versionRegistry->satisfies(PHP_VERSION, $constraint)) {
                    $issues[] = "Module '{$name}' requires PHP {$constraint}, but current is " . PHP_VERSION;
                }
            } else {
                // Check other module dependencies
                $dependencyManifest = $availableManifests[$dependency] ?? null;

                if ($dependencyManifest === null) {
                    $issues[] = "Module '{$name}' requires '{$dependency}' but it was not found";
                    continue;
                }

                $dependencyVersion = $dependencyManifest['version'] ?? '0.0.0';

                if (!$this->versionRegistry->satisfies($dependencyVersion, (string) $constraint)) {
                    $issues[] = "Module '{$name}' requires '{$dependency}' version {$constraint}, but found {$dependencyVersion}";
                }
            }
        }

        // Check for conflicts
        $conflicts = $manifest['conflicts'] ?? [];
        foreach ($conflicts as $conflictingModule) {
            if ($this->has($conflictingModule)) {
                $issues[] = "Module '{$name}' conflicts with '{$conflictingModule}'";
            }
        }

        return $issues;
    }

    /** @param array<string, array> $modules */
    public function resolveLoadOrder(array $modules): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$sorted, &$visited, &$visiting, $modules) {
            if (isset($visited[$name])) {
                return;
            }

            if (isset($visiting[$name])) {
                throw new ModuleException("Circular dependency detected involving module '{$name}'");
            }

            $visiting[$name] = true;

            $manifest = $modules[$name] ?? [];
            $requires = $manifest['requires'] ?? [];

            foreach ($requires as $dependency => $constraint) {
                // Only consider module dependencies (not core/php)
                if ($dependency !== 'core' && $dependency !== 'php' && isset($modules[$dependency])) {
                    $visit($dependency);
                }
            }

            unset($visiting[$name]);
            $visited[$name] = true;
            $sorted[] = $name;
        };

        foreach (array_keys($modules) as $name) {
            $visit($name);
        }

        return $sorted;
    }
}
