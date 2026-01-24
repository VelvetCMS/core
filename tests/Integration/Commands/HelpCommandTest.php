<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Commands;

use PHPUnit\Framework\TestCase;
use VelvetCMS\Commands\CommandRegistry;
use VelvetCMS\Commands\HelpCommand;
use VelvetCMS\Commands\ListCommand;
use VelvetCMS\Commands\MigrateCommand;

class HelpCommandTest extends TestCase
{
    public function test_help_command_displays_command_usage(): void
    {
        $registry = new CommandRegistry();
        $registry->register('list', ListCommand::class);
        $registry->register('help', HelpCommand::class);
        $registry->register('migrate', MigrateCommand::class);

        $command = new HelpCommand($registry);
        $command->setArguments(['migrate']);
        $command->setOptions([]);

        ob_start();
        $exitCode = $command->handle();
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('migrate', $output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('--path', $output);
        $this->assertStringContainsString('--force', $output);
    }
}
