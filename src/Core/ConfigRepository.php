<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

use RuntimeException;

class ConfigRepository
{
    private static ?self $instance = null;

    private readonly string $configPath;
    private readonly string $userConfigPath;
    private readonly ?string $tenantConfigPath;

    /** @var array<string, mixed> */
    private array $items = [];

    /** @var array<string, bool> */
    private array $loaded = [];

    private bool $loadedFromCache = false;

    private ?string $cacheFile;

    private ?array $siteConfig = null;
    private bool $siteConfigChecked = false;

    public function __construct(string $configPath, ?string $cacheFile = null, ?string $userConfigPath = null, ?string $tenantConfigPath = null)
    {
        $this->configPath = rtrim($configPath, DIRECTORY_SEPARATOR);
        $this->cacheFile = $cacheFile;
        $this->userConfigPath = rtrim(
            $userConfigPath ?? (dirname($this->configPath) . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'config'),
            DIRECTORY_SEPARATOR
        );
        $this->tenantConfigPath = $tenantConfigPath !== null
            ? rtrim($tenantConfigPath, DIRECTORY_SEPARATOR)
            : null;

        if ($cacheFile && file_exists($cacheFile)) {
            $cached = require $cacheFile;
            if (is_array($cached)) {
                $this->items = $cached;
                foreach (array_keys($cached) as $name) {
                    $this->loaded[$name] = true;
                }
                $this->loadedFromCache = true;
            }
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $configPath = config_path();
            $cacheFile = storage_path('cache/config.php');
            $tenantId = \VelvetCMS\Core\Tenancy\TenancyManager::currentId();
            $tenantConfigPath = null;
            if ($tenantId !== null) {
                $tenancyConfig = \VelvetCMS\Core\Tenancy\TenancyManager::config();
                $userRoot = $tenancyConfig['paths']['user_root'] ?? 'user/tenants';
                $tenantConfigPath = base_path(trim((string) $userRoot, '/')) . DIRECTORY_SEPARATOR . $tenantId . DIRECTORY_SEPARATOR . 'config';
            }

            self::$instance = new self($configPath, $cacheFile, null, $tenantConfigPath);
        }

        return self::$instance;
    }

    public static function setInstance(self $repository): void
    {
        self::$instance = $repository;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (strpos($key, '.') === false) {
            $this->load($key);
            return $this->items[$key] ?? $default;
        }

        [$name, $path] = explode('.', $key, 2);
        $this->load($name);

        $value = $this->items[$name] ?? null;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        if (strpos($key, '.') === false) {
            $this->items[$key] = $value;
            $this->loaded[$key] = true;
            return;
        }

        [$name, $path] = explode('.', $key, 2);
        $this->load($name);

        $segments = explode('.', $path);
        $reference =& $this->items[$name];

        foreach ($segments as $segment) {
            if (!is_array($reference)) {
                $reference = [];
            }

            if (!array_key_exists($segment, $reference)) {
                $reference[$segment] = [];
            }

            $reference =& $reference[$segment];
        }

        $reference = $value;
    }

    public function loadAll(): void
    {
        if ($this->loadedFromCache) {
            return;
        }

        foreach (glob($this->configPath . '/*.php') as $file) {
            $name = basename($file, '.php');
            $this->load($name);
        }
    }

    public function cacheTo(string $destination): void
    {
        $this->loadAll();

        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $export = var_export($this->items, true);
        $content = <<<PHP
<?php

return {$export};
PHP;

    file_put_contents($destination, $content);

    $this->cacheFile = $destination;
    }

    public function clearCache(): void
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function all(): array
    {
        $this->loadAll();
        return $this->items;
    }

    private function load(string $name): void
    {
        if (isset($this->loaded[$name]) || $this->loadedFromCache) {
            return;
        }

        $path = $this->configPath . DIRECTORY_SEPARATOR . $name . '.php';

        if (!file_exists($path)) {
            $this->items[$name] = [];
            $this->loaded[$name] = true;
            return;
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException("Configuration file '{$path}' must return an array.");
        }

        $userConfigFile = $this->userConfigPath . DIRECTORY_SEPARATOR . $name . '.php';
        if (file_exists($userConfigFile)) {
            $userConfig = require $userConfigFile;
            if (is_array($userConfig)) {
                $config = array_replace_recursive($config, $userConfig);
            }
        }

        if ($this->tenantConfigPath !== null) {
            $tenantConfigFile = $this->tenantConfigPath . DIRECTORY_SEPARATOR . $name . '.php';
            if (file_exists($tenantConfigFile)) {
                $tenantConfig = require $tenantConfigFile;
                if (is_array($tenantConfig)) {
                    $config = array_replace_recursive($config, $tenantConfig);
                }
            }
        }

        $this->items[$name] = $config;
        $this->loaded[$name] = true;
    }

    public function persist(string $key, mixed $value): string
    {
        if (strpos($key, '.') === false) {
            throw new \InvalidArgumentException("Cannot set entire config file to a single value. Use 'file.key' notation.");
        }

        [$file, $path] = explode('.', $key, 2);

        $userConfigDir = $this->userConfigPath;
        if (!is_dir($userConfigDir)) {
            mkdir($userConfigDir, 0755, true);
        }

        $filePath = $userConfigDir . '/' . $file . '.php';
        $config = [];

        if (file_exists($filePath)) {
            $config = require $filePath;
            if (!is_array($config)) {
                $config = [];
            }
        }

        $this->setNested($config, $path, $value);

        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($filePath, $content);

        $this->set($key, $value);

        return $filePath;
    }

    private function setNested(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }
}
