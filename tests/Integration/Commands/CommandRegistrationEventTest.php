<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Commands;

use PHPUnit\Framework\TestCase;
use VelvetCMS\Commands\CommandRegistry;
use VelvetCMS\Tests\Fixtures\Commands\DemoCommand;

final class CommandRegistrationEventTest extends TestCase
{
    public function test_modules_can_register_commands_via_event(): void
    {
        $app = require base_path('bootstrap/app.php');
        $app->boot();

        $registry = new CommandRegistry();
        $app->instance(CommandRegistry::class, $registry);
        $app->alias('commands', CommandRegistry::class);

        $events = $app->make('events');

        $received = null;
        $events->listen('commands.registering', function(CommandRegistry $commands) use (&$received): void {
            $received = $commands;
            $commands->register('demo:test', DemoCommand::class);
        });

        $events->dispatch('commands.registering', $registry);

        $this->assertSame($registry, $received);
        $this->assertTrue($registry->has('demo:test'));
    }
}
