<?php

declare(strict_types=1);

namespace VelvetCMS\Commands;

use VelvetCMS\Database\Connection;
use VelvetCMS\Database\Migrations\MigrationRepository;
use VelvetCMS\Database\Migrations\Migrator;
use VelvetCMS\Database\Schema\Schema;

class InstallCommand extends Command
{
    private bool $interactive = true;
    private array $config = [];

    public static function category(): string
    {
        return 'Setup';
    }

    public function signature(): string
    {
        return 'install [--defaults] [--force] [--no-migrate] [--no-sample]';
    }
    
    public function description(): string
    {
        return 'Install VelvetCMS (interactive setup wizard)';
    }
    
    public function handle(): int
    {
        $this->interactive = !$this->option('defaults');
        $force = (bool) $this->option('force');
        
        $this->printBanner();
        
        if ($this->option('defaults')) {
            $this->info('Running with --defaults (non-interactive mode)');
            $this->line('');
        }
        
        if (!$force && $this->isInstalled()) {
            $this->warning('VelvetCMS appears to be already installed.');
            if (!$this->interactive || !$this->confirm('Run setup again?', false)) {
                $this->line('Use --force to re-run installation.');
                return 0;
            }
        }
        
        $this->step(1, 6, 'Creating directories');
        $this->createDirectories();
        
        $this->step(2, 6, 'Database configuration');
        $connection = $this->configureDatabase();
        
        $this->step(3, 6, 'Database migrations');
        if (!$this->option('no-migrate')) {
            $this->runMigrations($connection);
        } else {
            $this->line('  Skipped (--no-migrate)');
        }
        
        $this->step(4, 6, 'Content storage');
        $this->configureContentDriver();
        
        $this->step(5, 6, 'Cache configuration');
        $this->configureCacheDriver();
        
        $this->step(6, 6, 'Additional options');
        $this->configureAdditionalOptions();
        
        if (!$this->option('no-sample')) {
            $this->configureSampleContent();
        }
        
        $this->writeConfiguration();
        
        $this->printSuccess();
        
        return 0;
    }
    
    private function printBanner(): void
    {
        $this->line('');
        $this->line("\033[1;36m╔══════════════════════════════════════════╗\033[0m");
        $this->line("\033[1;36m║           VelvetCMS Core Setup           ║\033[0m");
        $this->line("\033[1;36m╚══════════════════════════════════════════╝\033[0m");
        $this->line('');
    }
    
    private function step(int $current, int $total, string $title): void
    {
        $this->line('');
        $this->info("[{$current}/{$total}] {$title}");
    }
    
    private function isInstalled(): bool
    {
        return file_exists(base_path('user/config/app.php')) 
            && is_dir(base_path('storage/cache'));
    }
    
    private function createDirectories(): void
    {
        $directories = [
            'storage/cache',
            'storage/logs',
            'storage/sessions',
            'user/modules',
            'user/config',
            'user/content/pages',
            'user/views/layouts',
        ];
        
        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
        
        $this->success('  Directories created');
    }
    
    private function configureDatabase(): ?Connection
    {
        $drivers = ['sqlite', 'mysql', 'pgsql'];
        $default = 'sqlite';
        
        if ($this->interactive) {
            $this->line('  Available drivers:');
            $this->line("    \033[1m[1] sqlite\033[0m - Zero config, file-based (recommended)");
            $this->line('    [2] mysql  - MySQL/MariaDB server');
            $this->line('    [3] pgsql  - PostgreSQL server');
            
            $choice = (int) $this->ask('  Select database driver', '1');
            $driver = $drivers[$choice - 1] ?? $default;
        } else {
            $driver = $default;
        }
        
        $this->config['db.default'] = $driver;
        
        if ($driver === 'sqlite') {
            $dbPath = base_path('storage/database.sqlite');
            $this->config['db.connections.sqlite.database'] = $dbPath;
            
            if (!file_exists($dbPath)) {
                touch($dbPath);
            }
            
            $this->success("  Using SQLite: storage/database.sqlite");
        } else {
            if ($this->interactive) {
                $defaultHost = '127.0.0.1';
                $defaultPort = $driver === 'pgsql' ? '5432' : '3306';
                $defaultUser = $driver === 'pgsql' ? 'postgres' : 'root';
                
                $host = $this->ask('  Database host', $defaultHost);
                $port = $this->ask('  Database port', $defaultPort);
                $database = $this->ask('  Database name', 'velvetcms');
                $username = $this->ask('  Username', $defaultUser);
                $password = $this->secret('  Password');
                
                $this->config["db.connections.{$driver}.host"] = $host;
                $this->config["db.connections.{$driver}.port"] = $port;
                $this->config["db.connections.{$driver}.database"] = $database;
                $this->config["db.connections.{$driver}.username"] = $username;
                $this->config["db.connections.{$driver}.password"] = $password;
            }
            
            $this->success("  Using {$driver}");
        }
        
        try {
            $connection = $this->buildConnection($driver);
            $connection->getPdo();
            $this->success('  Connection test: OK');
            return $connection;
        } catch (\Throwable $e) {
            $this->warning("  Connection test failed: {$e->getMessage()}");
            if ($this->interactive && $this->confirm('  Continue anyway?', true)) {
                return null;
            }
            if (!$this->interactive) {
                return null;
            }
            throw $e;
        }
    }
    
    private function buildConnection(string $driver): Connection
    {
        $config = config('db') ?? [];
        $config['default'] = $driver;
        
        foreach ($this->config as $key => $value) {
            if (str_starts_with($key, 'db.')) {
                $parts = explode('.', substr($key, 3));
                $ref = &$config;
                foreach ($parts as $part) {
                    if (!isset($ref[$part])) {
                        $ref[$part] = [];
                    }
                    $ref = &$ref[$part];
                }
                $ref = $value;
            }
        }
        
        return new Connection($config);
    }
    
    private function runMigrations(?Connection $connection): void
    {
        if ($connection === null) {
            $this->warning('  Skipped (no database connection)');
            return;
        }
        
        $runMigrations = true;
        if ($this->interactive) {
            $runMigrations = $this->confirm('  Run database migrations?', true);
        }
        
        if (!$runMigrations) {
            $this->line('  Skipped');
            return;
        }
        
        try {
            Schema::setConnection($connection);
            
            $repository = new MigrationRepository($connection);
            $migrator = new Migrator($connection, $repository);
            
            $path = base_path('database/migrations');
            if (is_dir($path)) {
                $migrator->run($path);
                $this->success('  Migrations completed');
            } else {
                $this->line('  No migrations found');
            }
        } catch (\Throwable $e) {
            $this->warning("  Migration failed: {$e->getMessage()}");
        }
    }
    
    private function configureContentDriver(): void
    {
        $drivers = ['file', 'db', 'hybrid', 'auto'];
        $default = 'file';
        
        if ($this->interactive) {
            $this->line('  Available drivers:');
            $this->line("    \033[1m[1] file\033[0m   - Markdown files with frontmatter (recommended)");
            $this->line('    [2] db     - All content in database');
            $this->line('    [3] hybrid - Metadata in DB, content in files');
            $this->line('    [4] auto   - Switches based on page count');
            
            $choice = (int) $this->ask('  Select content driver', '1');
            $driver = $drivers[$choice - 1] ?? $default;
        } else {
            $driver = $default;
        }
        
        $this->config['content.driver'] = $driver;
        $this->success("  Content driver: {$driver}");
    }
    
    private function configureCacheDriver(): void
    {
        $drivers = ['file', 'apcu', 'redis'];
        $default = 'file';
        
        if ($this->interactive) {
            $this->line('  Available drivers:');
            $this->line("    \033[1m[1] file\033[0m  - File-based cache (works everywhere)");
            $this->line('    [2] apcu  - In-memory (requires APCu extension)');
            $this->line('    [3] redis - Redis server (requires connection)');
            
            $choice = (int) $this->ask('  Select cache driver', '1');
            $driver = $drivers[$choice - 1] ?? $default;
        } else {
            $driver = $default;
        }
        
        $this->config['cache.default'] = $driver;
        
        if ($driver === 'redis' && $this->interactive) {
            $host = $this->ask('  Redis host', '127.0.0.1');
            $port = $this->ask('  Redis port', '6379');
            $this->config['cache.drivers.redis.host'] = $host;
            $this->config['cache.drivers.redis.port'] = (int) $port;
        }
        
        $this->success("  Cache driver: {$driver}");
    }
    
    private function configureAdditionalOptions(): void
    {
        $this->configureMarkdownParser();
        $this->configureMultiTenancy();
    }
    
    private function configureMarkdownParser(): void
    {
        $drivers = ['commonmark', 'parsedown', 'html'];
        
        $hasCommonmark = class_exists(\League\CommonMark\MarkdownConverter::class);
        $hasParsedown = class_exists('Parsedown');
        
        $default = $hasCommonmark ? 'commonmark' : ($hasParsedown ? 'parsedown' : 'html');
        
        if ($this->interactive) {
            $this->line('  Markdown parser:');
            $cm = $hasCommonmark ? '' : " \033[33m(not installed)\033[0m";
            $pd = $hasParsedown ? '' : " \033[33m(not installed)\033[0m";
            $this->line("    [1] commonmark - Full-featured{$cm}");
            $this->line("    [2] parsedown  - Fast & simple{$pd}");
            $this->line('    [3] html       - No parsing (raw HTML only)');
            
            $defaultIndex = array_search($default, $drivers) + 1;
            $choice = (int) $this->ask('  Select parser', (string) $defaultIndex);
            $driver = $drivers[$choice - 1] ?? $default;
        } else {
            $driver = $default;
        }
        
        $this->config['content.parser.driver'] = $driver;
        
        if ($driver === 'commonmark' && !$hasCommonmark) {
            $this->warning("  Note: Run 'composer require league/commonmark'");
        } elseif ($driver === 'parsedown' && !$hasParsedown) {
            $this->warning("  Note: Run 'composer require erusev/parsedown'");
        }
        
        $this->success("  Markdown parser: {$driver}");
    }
    
    private function configureMultiTenancy(): void
    {
        $enabled = false;
        
        if ($this->interactive) {
            $enabled = $this->confirm('  Enable multi-tenancy?', false);
        }
        
        $this->config['tenancy.enabled'] = $enabled;
        
        if ($enabled && $this->interactive) {
            $this->line('  Tenant resolver:');
            $this->line("    \033[1m[1] host\033[0m - Based on hostname/subdomain");
            $this->line('    [2] path - Based on URL path segment');
            
            $choice = $this->ask('  Select resolver', '1');
            $resolver = $choice === '2' ? 'path' : 'host';
            $this->config['tenancy.resolver'] = $resolver;
            
            $this->success("  Multi-tenancy: enabled ({$resolver} resolver)");
        } else {
            $this->line('  Multi-tenancy: disabled');
        }
    }
    
    private function configureSampleContent(): void
    {
        $createSamples = true;
        
        if ($this->interactive) {
            $createSamples = $this->confirm('  Create sample content?', true);
        }
        
        if (!$createSamples) {
            return;
        }
        
        $userContentPath = base_path('user/content/pages');
        $stubContentPath = base_path('src/stubs/defaults/content/pages');
        
        if (is_dir($stubContentPath)) {
            $files = glob($stubContentPath . '/*');
            foreach ($files as $file) {
                $filename = basename($file);
                $target = $userContentPath . '/' . $filename;
                if (!file_exists($target)) {
                    copy($file, $target);
                }
            }
        }
        
        $userViewsPath = base_path('user/views');
        $stubViewsPath = base_path('src/stubs/defaults/views');
        
        if (is_dir($stubViewsPath)) {
            $this->recursiveCopy($stubViewsPath, $userViewsPath);
        }
        
        $this->success('  Sample content created');
    }
    
    private function writeConfiguration(): void
    {
        $this->ensureUserConfig();
        $this->updateUserConfigFiles();
        $this->success('  Configuration saved');
    }
    
    private function ensureUserConfig(): void
    {
        $userConfigDir = base_path('user/config');
        if (!is_dir($userConfigDir)) {
            mkdir($userConfigDir, 0755, true);
        }

        $defaultConfigDir = config_path('');
        $files = glob(rtrim($defaultConfigDir, '/') . '/*.php');

        foreach ($files as $file) {
            $filename = basename($file);
            $target = $userConfigDir . '/' . $filename;
            if (!file_exists($target)) {
                copy($file, $target);
            }
        }
    }
    
    private function updateUserConfigFiles(): void
    {
        if (isset($this->config['db.default'])) {
            $this->updateConfigValue('db.php', "'default'", $this->config['db.default']);
        }
        
        $driver = $this->config['db.default'] ?? 'sqlite';
        if ($driver !== 'sqlite') {
            $connPrefix = "db.connections.{$driver}.";
            foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
                if (isset($this->config[$connPrefix . $key])) {
                    $this->updateNestedConfigValue(
                        'db.php',
                        ['connections', $driver, $key],
                        $this->config[$connPrefix . $key]
                    );
                }
            }
        }
        
        if (isset($this->config['cache.default'])) {
            $this->updateConfigValue('cache.php', "'default'", $this->config['cache.default']);
        }
        
        if (isset($this->config['content.driver'])) {
            $this->updateConfigValue('content.php', "'driver'", $this->config['content.driver']);
        }
        
        if (isset($this->config['content.parser.driver'])) {
            $this->updateParserDriver($this->config['content.parser.driver']);
        }
        
        if (isset($this->config['tenancy.enabled'])) {
            $this->updateConfigValue('tenancy.php', "'enabled'", $this->config['tenancy.enabled']);
        }
        
        if (isset($this->config['tenancy.resolver'])) {
            $this->updateConfigValue('tenancy.php', "'resolver'", $this->config['tenancy.resolver']);
        }
    }
    
    private function updateConfigValue(string $file, string $key, mixed $value): void
    {
        $path = base_path('user/config/' . $file);
        if (!file_exists($path)) {
            return;
        }
        
        $contents = file_get_contents($path);
        $quotedValue = is_bool($value) ? ($value ? 'true' : 'false') : "'" . addslashes((string) $value) . "'";
        
        // Match pattern: 'key' => env(..., 'value') or 'key' => 'value'
        $pattern = "/({$key}\s*=>\s*)(?:env\([^,]+,\s*)?(['\"])[^'\"]*\2\)?/";
        $replacement = '$1' . $quotedValue;
        
        $updated = preg_replace($pattern, $replacement, $contents, 1);
        
        if ($updated !== null && $updated !== $contents) {
            file_put_contents($path, $updated);
        }
    }
    
    private function updateNestedConfigValue(string $file, array $keys, mixed $value): void
    {
        $path = base_path('user/config/' . $file);
        if (!file_exists($path)) {
            return;
        }
        
        $contents = file_get_contents($path);
        $quotedValue = is_bool($value) ? ($value ? 'true' : 'false') : "'" . addslashes((string) $value) . "'";
        
        // Build pattern for nested key like 'connections' => ['mysql' => ['host' => ...]]
        $lastKey = array_pop($keys);
        $pattern = "/('{$lastKey}'\s*=>\s*)(?:env\([^,]+,\s*)?(['\"])[^'\"]*\2\)?/";
        
        $updated = preg_replace($pattern, '$1' . $quotedValue, $contents, 1);
        
        if ($updated !== null && $updated !== $contents) {
            file_put_contents($path, $updated);
        }
    }
    
    private function updateParserDriver(string $driver): void
    {
        $path = base_path('user/config/content.php');
        if (!file_exists($path)) {
            return;
        }
        
        $contents = file_get_contents($path);
        
        // Look for parser driver specifically within the parser array
        $pattern = "/('parser'\s*=>\s*\[.*?'driver'\s*=>\s*)(?:env\([^,]+,\s*)?(['\"])[^'\"]*\2\)?/s";
        $replacement = '$1' . "'{$driver}'";
        
        $updated = preg_replace($pattern, $replacement, $contents, 1);
        
        if ($updated !== null && $updated !== $contents) {
            file_put_contents($path, $updated);
        }
    }
    
    private function printSuccess(): void
    {
        $this->line('');
        $this->line("\033[32m╔══════════════════════════════════════════╗\033[0m");
        $this->line("\033[32m║  VelvetCMS Core installed successfully!  ║\033[0m");
        $this->line("\033[32m╚══════════════════════════════════════════╝\033[0m");
        $this->line('');
        $this->info('Next steps:');
        $this->line("  1. Review config in \033[36muser/config/\033[0m");
        $this->line("  2. Start server:    \033[36mvelvet serve\033[0m");
        $this->line("  3. Visit:           \033[36mhttp://localhost:8000\033[0m");
        $this->line('');
    }
    
    private function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        
        while (($file = readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;
                
                if (is_dir($srcPath)) {
                    $this->recursiveCopy($srcPath, $dstPath);
                } elseif (!file_exists($dstPath)) {
                    copy($srcPath, $dstPath);
                }
            }
        }
        closedir($dir);
    }
}
