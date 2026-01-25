<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Scheduling;

use VelvetCMS\Core\Application;
use VelvetCMS\Scheduling\Task;
use VelvetCMS\Tests\Support\TestCase;

final class TaskTest extends TestCase
{
    // === Cron Expression: Wildcard ===

    public function test_wildcard_always_matches(): void
    {
        $task = new Task(fn() => null);
        $task->everyMinute(); // * * * * *

        $this->assertTrue($task->isDue());
    }

    // === Cron Expression: Exact Match ===

    public function test_exact_minute_matches(): void
    {
        $task = $this->createTaskWithExpression(
            (int) date('i') . ' * * * *'
        );

        $this->assertTrue($task->isDue());
    }

    public function test_exact_minute_does_not_match(): void
    {
        $wrongMinute = ((int) date('i') + 1) % 60;
        $task = $this->createTaskWithExpression("{$wrongMinute} * * * *");

        $this->assertFalse($task->isDue());
    }

    public function test_exact_hour_matches(): void
    {
        $minute = (int) date('i');
        $hour = (int) date('H');
        $task = $this->createTaskWithExpression("{$minute} {$hour} * * *");

        $this->assertTrue($task->isDue());
    }

    public function test_exact_hour_does_not_match(): void
    {
        $minute = (int) date('i');
        $wrongHour = ((int) date('H') + 1) % 24;
        $task = $this->createTaskWithExpression("{$minute} {$wrongHour} * * *");

        $this->assertFalse($task->isDue());
    }

    // === Cron Expression: Comma Lists ===

    public function test_comma_list_matches_current_value(): void
    {
        $minute = (int) date('i');
        $otherMinute = ($minute + 5) % 60;
        $task = $this->createTaskWithExpression("{$minute},{$otherMinute} * * * *");

        $this->assertTrue($task->isDue());
    }

    public function test_comma_list_does_not_match_unlisted_value(): void
    {
        $minute = (int) date('i');
        $other1 = ($minute + 1) % 60;
        $other2 = ($minute + 2) % 60;
        $task = $this->createTaskWithExpression("{$other1},{$other2} * * * *");

        $this->assertFalse($task->isDue());
    }

    // === Cron Expression: Step Values ===

    public function test_step_value_matches_when_divisible(): void
    {
        // */1 should always match any minute
        $task = $this->createTaskWithExpression('*/1 * * * *');
        $this->assertTrue($task->isDue());
    }

    public function test_step_value_every_two_minutes(): void
    {
        $minute = (int) date('i');
        $task = $this->createTaskWithExpression('*/2 * * * *');

        // This should match when minute is divisible by 2
        $this->assertSame($minute % 2 === 0, $task->isDue());
    }

    public function test_step_value_every_five_minutes(): void
    {
        $minute = (int) date('i');
        $task = $this->createTaskWithExpression('*/5 * * * *');

        $this->assertSame($minute % 5 === 0, $task->isDue());
    }

    public function test_step_value_every_fifteen_minutes(): void
    {
        $minute = (int) date('i');
        $task = $this->createTaskWithExpression('*/15 * * * *');

        $this->assertSame($minute % 15 === 0, $task->isDue());
    }

    // === Cron Expression: Invalid ===

    public function test_invalid_expression_with_wrong_parts_count_returns_false(): void
    {
        $task = $this->createTaskWithExpression('* * *'); // Only 3 parts
        $this->assertFalse($task->isDue());
    }

    public function test_invalid_expression_with_too_many_parts_returns_false(): void
    {
        $task = $this->createTaskWithExpression('* * * * * *'); // 6 parts
        $this->assertFalse($task->isDue());
    }

    // === Frequency Methods ===

    public function test_every_minute_sets_correct_expression(): void
    {
        $task = new Task(fn() => null);
        $task->everyMinute();

        // Every minute should always be due
        $this->assertTrue($task->isDue());
    }

    public function test_hourly_sets_correct_expression(): void
    {
        $task = new Task(fn() => null);
        $task->hourly();

        // Hourly = 0 * * * * (minute 0 of every hour)
        $minute = (int) date('i');
        $this->assertSame($minute === 0, $task->isDue());
    }

    public function test_daily_sets_correct_expression(): void
    {
        $task = new Task(fn() => null);
        $task->daily();

        // Daily = 0 0 * * * (midnight)
        $minute = (int) date('i');
        $hour = (int) date('H');
        $this->assertSame($minute === 0 && $hour === 0, $task->isDue());
    }

    public function test_daily_at_sets_correct_time(): void
    {
        $task = new Task(fn() => null);
        $hour = (int) date('H');
        $minute = (int) date('i');
        $task->dailyAt($hour, $minute);

        $this->assertTrue($task->isDue());
    }

    public function test_daily_at_with_different_time(): void
    {
        $task = new Task(fn() => null);
        $wrongHour = ((int) date('H') + 1) % 24;
        $task->dailyAt($wrongHour, 30);

        $this->assertFalse($task->isDue());
    }

    // === Command Tasks ===

    public function test_command_factory_creates_task(): void
    {
        $task = Task::command('cache:clear', ['--force' => true]);

        $this->assertSame('cache:clear', $task->getCommand());
        $this->assertSame(['--force' => true], $task->getParameters());
    }

    public function test_callback_task_has_null_command(): void
    {
        $task = new Task(fn() => 'result');

        $this->assertNull($task->getCommand());
    }

    // === Task Execution ===

    public function test_callback_task_runs_callback(): void
    {
        $ran = false;
        $task = new Task(function () use (&$ran) {
            $ran = true;
        });

        $task->run(new Application($this->tmpDir));

        $this->assertTrue($ran);
    }

    public function test_callback_receives_parameters(): void
    {
        $received = null;
        $task = new Task(
            function ($a, $b) use (&$received) {
                $received = [$a, $b];
            },
            ['first', 'second']
        );

        $task->run(new Application($this->tmpDir));

        $this->assertSame(['first', 'second'], $received);
    }

    // === Fluent Interface ===

    public function test_frequency_methods_return_self(): void
    {
        $task = new Task(fn() => null);

        $this->assertSame($task, $task->everyMinute());
        $this->assertSame($task, $task->hourly());
        $this->assertSame($task, $task->daily());
        $this->assertSame($task, $task->dailyAt(12, 0));
    }

    // === Day/Month/Weekday Matching ===

    public function test_matches_current_day_of_month(): void
    {
        $minute = (int) date('i');
        $hour = (int) date('H');
        $day = (int) date('d');
        $task = $this->createTaskWithExpression("{$minute} {$hour} {$day} * *");

        $this->assertTrue($task->isDue());
    }

    public function test_does_not_match_wrong_day_of_month(): void
    {
        $minute = (int) date('i');
        $hour = (int) date('H');
        $wrongDay = ((int) date('d') % 28) + 1; // Different day (safe for all months)
        if ($wrongDay === (int) date('d')) {
            $wrongDay = ($wrongDay % 28) + 1;
        }
        $task = $this->createTaskWithExpression("{$minute} {$hour} {$wrongDay} * *");

        $this->assertFalse($task->isDue());
    }

    public function test_matches_current_month(): void
    {
        $minute = (int) date('i');
        $hour = (int) date('H');
        $day = (int) date('d');
        $month = (int) date('m');
        $task = $this->createTaskWithExpression("{$minute} {$hour} {$day} {$month} *");

        $this->assertTrue($task->isDue());
    }

    public function test_matches_current_weekday(): void
    {
        $minute = (int) date('i');
        $hour = (int) date('H');
        $weekday = (int) date('w'); // 0 = Sunday
        $task = $this->createTaskWithExpression("{$minute} {$hour} * * {$weekday}");

        $this->assertTrue($task->isDue());
    }

    /**
     * Helper to create a task with a specific cron expression.
     */
    private function createTaskWithExpression(string $expression): Task
    {
        $task = new Task(fn() => null);
        // Use reflection to set the expression directly
        $ref = new \ReflectionClass($task);
        $prop = $ref->getProperty('expression');
        $prop->setValue($task, $expression);
        return $task;
    }
}
