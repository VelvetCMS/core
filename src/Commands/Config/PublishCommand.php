<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Config;

use VelvetCMS\Commands\Command;

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
        return 'Publish a default configuration file to user/config';
    }

    public function handle(): int
    {
        $file = $this->argument(0);
        if (!$file) {
            $this->info('Usage: velvet config:publish <file>');
            return 1;
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
}
