<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Config;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\ConfigRepository;

class PublishCommand extends Command
{
    public static function category(): string
    {
        return 'Config';
    }

    public function signature(): string
    {
        return 'config:publish {file}';
    }

    public function description(): string
    {
        return 'Publish a default configuration file to user/config (use namespace:file for module configs)';
    }

    public function handle(): int
    {
        $file = $this->argument(0);
        if (!$file) {
            $this->info('Usage: velvet config:publish <file>');
            $this->info('       velvet config:publish <namespace>:<file>');
            return 1;
        }

        if (str_contains($file, ':')) {
            return $this->publishNamespaced($file);
        }

        $source = config_path($file . '.php');
        if (!file_exists($source)) {
            $this->info("Configuration file '{$file}' not found in defaults.");
            return 1;
        }

        $userConfigDir = base_path('user/config');
        if (!is_dir($userConfigDir)) {
            mkdir($userConfigDir, 0755, true);
        }

        $dest = $userConfigDir . '/' . $file . '.php';
        if (file_exists($dest)) {
            $this->info("File '{$dest}' already exists.");
            return 1;
        }

        copy($source, $dest);
        $this->info("Configuration published to user/config/{$file}.php");
        return 0;
    }

    private function publishNamespaced(string $key): int
    {
        [$namespace, $file] = explode(':', $key, 2);

        /** @var ConfigRepository $repo */
        $repo = app(ConfigRepository::class);
        $namespacePaths = $repo->getNamespacePaths();

        $modulePath = $namespacePaths[$namespace] ?? null;
        if ($modulePath === null) {
            $this->info("Module namespace '{$namespace}' is not registered.");
            return 1;
        }

        $source = $modulePath . '/' . $file . '.php';
        if (!file_exists($source)) {
            $this->info("Configuration file '{$file}' not found in module '{$namespace}'.");
            return 1;
        }

        $destDir = base_path('user/config/' . $namespace);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $dest = $destDir . '/' . $file . '.php';
        if (file_exists($dest)) {
            $this->info("File '{$dest}' already exists.");
            return 1;
        }

        copy($source, $dest);
        $this->info("Configuration published to user/config/{$namespace}/{$file}.php");
        return 0;
    }
}
