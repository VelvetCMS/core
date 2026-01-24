<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Config;

use VelvetCMS\Commands\Command;
use VelvetCMS\Core\ConfigRepository;

class ListCommand extends Command
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
        return 'config:list';
    }

    public function description(): string
    {
        return 'List all configuration values';
    }

    public function handle(): int
    {
        $all = $this->config->all();
        $this->printArray($all);
        return 0;
    }

    private function printArray(array $data, int $indent = 0): void
    {
        foreach ($data as $key => $value) {
            $prefix = str_repeat('  ', $indent);
            if (is_array($value)) {
                echo "{$prefix}\033[33m{$key}\033[0m:\n";
                $this->printArray($value, $indent + 1);
            } else {
                $valStr = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                echo "{$prefix}\033[32m{$key}\033[0m: {$valStr}\n";
            }
        }
    }
}
