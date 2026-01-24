<?php

declare(strict_types=1);

namespace VelvetCMS\Commands;

use VelvetCMS\Core\Application;
use VelvetCMS\Database\Connection;

class InstallCommand extends Command
{
    public static function category(): string
    {
        return 'Setup';
    }

    public function signature(): string
    {
        return 'install [--force]';
    }
    
    public function description(): string
    {
        return 'Install VelvetCMS (setup directories, migrations, config)';
    }
    
    public function handle(): int
    {
        $this->line();
        $this->line("\033[1;36m╔══════════════════════════════════════╗\033[0m");
        $this->line("\033[1;36m║      Welcome to VelvetCMS Core!      ║\033[0m");
        $this->line("\033[1;36m╚══════════════════════════════════════╝\033[0m");
        $this->line();
        
        $this->info('[1/4] Creating storage directories...');
        $this->createDirectories();
        
        $this->info('[2/4] Setting up configuration...');
        $this->ensureUserConfig();
        $this->configureMarkdownParser((bool) $this->option('force'));
        
        $this->info('[3/4] Running database migrations...');
        
        try {
            require_once base_path('vendor/autoload.php');

            $migrationsPath = base_path('database/migrations');
            $files = glob($migrationsPath . '/*.sql');

            if (!empty($files)) {
                $connection = $this->resolveDatabaseConnection();

                if ($connection === null) {
                    $this->warning('  ⚠ Unable to establish database connection, skipping migrations');
                } else {
                    $pdo = $connection->getPdo();

                    foreach ($files as $file) {
                        $sql = file_get_contents($file);
                        $pdo->exec($sql);
                    }

                    $this->success('  ✓ Migrations completed');
                }
            } else {
                $this->line('  ✓ No migrations to run');
            }
        } catch (\Throwable $e) {
            $this->warning("  ⚠ Migration skipped: {$e->getMessage()}");
        }
        
        $this->info('[4/4] Setting up sample content...');
        $this->ensureSamplePages();
        
        $this->line();
        $this->line('╔══════════════════════════════════════╗');
        $this->line('║VelvetCMS Core installed successfully!║');
        $this->line('╚══════════════════════════════════════╝');
        $this->line();
        $this->info('Next steps:');
        $this->line("  1. Check out the docs: \033[1;36mhttps://velvetcms.com/docs\033[0m");
        $this->line("  2. Start dev server: \033[1;36mvelvet serve\033[0m");
        $this->line("  3. Visit: \033[1;36mhttp://localhost:8000\033[0m");
        $this->line();
        
        return 0;
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
            'user/views',
        ];
        
        foreach ($directories as $dir) {
            $path = base_path($dir);
            
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->line("  ✓ Created: {$dir}");
            }
        }
        
        $this->success('  ✓ All directories created');
    }
    
    private function ensureUserConfig(): void
    {
        $userConfigDir = base_path('user/config');
        if (!is_dir($userConfigDir)) {
            mkdir($userConfigDir, 0755, true);
        }

        $defaultConfigDir = config_path('');
        $files = glob(rtrim($defaultConfigDir, '/') . '/*.php');

        if (!empty($files)) {
            foreach ($files as $file) {
                $filename = basename($file);
                $target = $userConfigDir . '/' . $filename;

                if (!file_exists($target)) {
                    copy($file, $target);
                    $this->line("  ✓ Created user/config/{$filename}");
                }
            }
        }
    }

    private function configureMarkdownParser(bool $force = false): void
    {
        $contentConfigPath = base_path('user/config/content.php');

        if (!file_exists($contentConfigPath)) {
            $this->warning('  ⚠ user/config/content.php not found, skipping markdown parser selection');
            return;
        }

        $current = $this->readParserDriverFromConfig($contentConfigPath);
        if ($current !== null && !$force) {
            if (!$this->confirm("Markdown parser is set to '{$current}'. Change it?", false)) {
                $this->line("  ✓ Markdown parser kept as '{$current}'");
                return;
            }
        }

        $this->line('  Markdown parser options:');
        $this->line('    - commonmark (requires league/commonmark)');
        $this->line('    - parsedown (requires erusev/parsedown)');
        $this->line('    - html (no markdown parsing)');

        $choices = ['commonmark', 'parsedown', 'html'];
        $defaultDriver = $current ?? 'commonmark';
        $defaultIndex = (string) (array_search($defaultDriver, $choices, true) ?: 0);

        $selected = $this->choice('Select parser', $choices, $defaultIndex);
        $driver = is_string($selected) ? $selected : $defaultDriver;

        if (!$this->updateParserDriverInConfig($contentConfigPath, $driver)) {
            $this->warning('  ⚠ Unable to update user/config/content.php with parser choice');
            return;
        }

        if ($driver === 'commonmark' && !class_exists(\League\CommonMark\MarkdownConverter::class)) {
            $this->warning("  ⚠ 'commonmark' selected but league/commonmark is not installed");
        }

        if ($driver === 'parsedown' && !class_exists('Parsedown')) {
            $this->warning("  ⚠ 'parsedown' selected but erusev/parsedown is not installed");
        }

        $this->line("  ✓ Markdown parser set to '{$driver}'");
    }

    private function readParserDriverFromConfig(string $path): ?string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (preg_match("/'driver'\s*=>\s*env\(\s*'CONTENT_PARSER_DRIVER'\s*,\s*'([^']+)'\s*\)\s*,/", $contents, $matches)) {
            return $matches[1];
        }

        if (preg_match("/'driver'\s*=>\s*'([^']+)'\s*,/", $contents, $matches)) {
            return $matches[1];
        }

        if (preg_match("/'parser'\s*=>\s*\[.*?'driver'\s*=>\s*'([^']+)'\s*,/s", $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function updateParserDriverInConfig(string $path, string $driver): bool
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        $updated = preg_replace(
            "/('driver'\s*=>\s*env\(\s*'CONTENT_PARSER_DRIVER'\s*,\s*')([^']*)('\s*\)\s*,)/",
            '$1' . $driver . '$3',
            $contents,
            1,
            $count
        );

        if (($count ?? 0) === 0) {
            $updated = preg_replace(
                "/('driver'\s*=>\s*')([^']*)(')/",
                '$1' . $driver . '$3',
                $contents,
                1,
                $count
            );
        }

        if (($count ?? 0) === 0) {
            $updated = preg_replace(
                "/('parser'\s*=>\s*\[.*?'driver'\s*=>\s*')([^']*)(')/s",
                '$1' . $driver . '$3',
                $contents,
                1,
                $count
            );
        }

        if (($count ?? 0) === 0 || $updated === null) {
            return false;
        }

        if ($updated !== $contents) {
            file_put_contents($path, $updated);
        }

        return true;
    }
    
    private function ensureSamplePages(): void
    {
        $userContentPath = base_path('user/content/pages');
        $stubContentPath = base_path('src/stubs/defaults/content/pages');
        
        if (is_dir($stubContentPath)) {
            $files = glob($stubContentPath . '/*');
            foreach ($files as $file) {
                $filename = basename($file);
                $target = $userContentPath . '/' . $filename;
                if (!file_exists($target)) {
                    copy($file, $target);
                    $this->line("  ✓ Created sample page: {$filename}");
                }
            }
        }

        $userViewsPath = base_path('user/views');
        $stubViewsPath = base_path('src/stubs/defaults/views');

        $defaultLayout = $userViewsPath . '/layouts/default.velvet.php';
        if (!file_exists($defaultLayout) && is_dir($stubViewsPath)) {
            $this->recursiveCopy($stubViewsPath, $userViewsPath);
            $this->line('  ✓ Installed default views');
        }
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        
        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function resolveDatabaseConnection(): ?Connection
    {
        if (isset($GLOBALS['app']) && $GLOBALS['app'] instanceof Application) {
            /** @var Application $app */
            $app = $GLOBALS['app'];

            if ($app->has('db')) {
                try {
                    $connection = $app->make('db');
                    if ($connection instanceof Connection) {
                        return $connection;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        try {
            $config = config('db');

            if (is_array($config)) {
                return new Connection($config);
            }
        } catch (\Throwable $e) {
        }

        return null;
    }
}
