<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use VelvetCMS\Core\Application;
use VelvetCMS\Http\Controllers\WebCronController;
use VelvetCMS\Scheduling\Schedule;
use VelvetCMS\Tests\Support\TestCase;

final class WebCronControllerTest extends TestCase
{
    public function test_denies_invalid_token(): void
    {
        config(['app.cron_token' => 'secret']);
        $_GET = [];
        http_response_code(200);

        $controller = new WebCronController(new Application($this->tmpDir), new Schedule());

        ob_start();
        $controller->run();
        $output = ob_get_clean();

        $this->assertSame(403, http_response_code());
        $this->assertStringContainsString('Forbidden', $output);
    }

    public function test_runs_due_tasks_and_outputs_count(): void
    {
        config(['app.cron_token' => 'secret']);
        $_GET = ['token' => 'secret'];

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        });
        $schedule->call(function () use (&$ran) {
            $ran++;
        });

        $controller = new WebCronController(new Application($this->tmpDir), $schedule);

        ob_start();
        $controller->run();
        $output = ob_get_clean();

        $this->assertSame(2, $ran);
        $this->assertStringContainsString('Ran 2 scheduled tasks.', $output);
    }
}
