<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Config;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\ConfigRepository;

class SetCommand extends Command
{
    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    public static function category(): string
    {
        return 'Config';
    }

    public function signature(): string
    {
        return 'config:set {key} {value}';
    }

    public function description(): string
    {
        return 'Set a configuration value';
    }

    public function handle(): int
    {
        $key = $this->argument(0);
        $value = $this->argument(1);

        if (!$key) {
            $key = $this->ask("Configuration key (e.g. app.debug)");
            if (!$key) {
                $this->error("Key is required.");
                return 1;
            }
        }

        if ($value === null) {
            $value = $this->ask("Value");
        }

        if ($value === 'true') $value = true;
        elseif ($value === 'false') $value = false;
        elseif (is_numeric($value)) $value = (strpos($value, '.') !== false) ? (float)$value : (int)$value;

        try {
            $filePath = $this->config->persist($key, $value);
            $this->info("Configuration saved to " . str_replace(base_path() . '/', '', $filePath));
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
