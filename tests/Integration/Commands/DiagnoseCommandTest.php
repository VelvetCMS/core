<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Commands;

use PHPUnit\Framework\TestCase;
use VelvetCMS\Commands\DiagnoseCommand;

class DiagnoseCommandTest extends TestCase
{
    public function test_diagnose_command_outputs_json_report(): void
    {
        $cacheDir = storage_path('cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        if (!file_exists(storage_path('database.sqlite'))) {
            touch(storage_path('database.sqlite'));
        }

        $app = require base_path('bootstrap/app.php');
        $app->boot();

        /** @var DiagnoseCommand $command */
        $command = $app->make(DiagnoseCommand::class);
        $command->setArguments([]);
        $command->setOptions(['json' => true]);

        ob_start();
        $exitCode = $command->handle();
        $output = trim((string) ob_get_clean());

        $this->assertSame(0, $exitCode, $output);
        $this->assertNotSame('', $output);

        $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('cache', $decoded);
        $this->assertArrayHasKey('storage', $decoded);
        $this->assertArrayHasKey('database', $decoded);
        $this->assertArrayHasKey('content_driver', $decoded);
        $this->assertArrayHasKey('modules', $decoded);
    }
}
