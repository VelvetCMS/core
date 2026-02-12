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
        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => false,
        ]);
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
        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => false,
        ]);
        $_GET = ['token' => 'secret'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

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

    public function test_denies_ip_not_on_allowlist(): void
    {
        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => ['10.0.0.1'],
        ]);

        $_GET = ['token' => 'secret'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        http_response_code(200);

        $controller = new WebCronController(new Application($this->tmpDir), new Schedule());

        ob_start();
        $controller->run();
        $output = ob_get_clean();

        $this->assertSame(403, http_response_code());
        $this->assertStringContainsString('IP not allowed', $output);
    }

    public function test_accepts_valid_signed_url_when_enabled(): void
    {
        $token = 'secret';
        $expires = time() + 120;
        $signature = hash_hmac('sha256', (string) $expires, $token);

        config([
            'app.cron_token' => $token,
            'app.cron_signed_urls' => true,
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => false,
        ]);

        $_GET = [
            'expires' => (string) $expires,
            'signature' => $signature,
        ];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        });

        $controller = new WebCronController(new Application($this->tmpDir), $schedule);

        ob_start();
        $controller->run();
        $output = ob_get_clean();

        $this->assertSame(1, $ran);
        $this->assertStringContainsString('Ran 1 scheduled tasks.', $output);
    }

    public function test_rate_limit_blocks_when_exceeded(): void
    {
        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => true,
            'app.cron_rate_limit.attempts' => 1,
            'app.cron_rate_limit.decay' => 60,
        ]);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $schedule = new Schedule();
        $controller = new WebCronController(new Application($this->tmpDir), $schedule);

        $_GET = ['token' => 'secret'];
        ob_start();
        $controller->run();
        ob_end_clean();

        $_GET = ['token' => 'secret'];
        http_response_code(200);
        ob_start();
        $controller->run();
        $output = ob_get_clean();

        $this->assertSame(429, http_response_code());
        $this->assertStringContainsString('Too Many Requests', $output);
    }
}
