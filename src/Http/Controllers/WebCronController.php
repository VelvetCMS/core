<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Controllers;

use VelvetCMS\Core\Application;
use VelvetCMS\Scheduling\Schedule;

class WebCronController
{
    public function __construct(
        private readonly Application $app,
        private readonly Schedule $schedule
    ) {}

    public function run(): void
    {
        $token = $_GET['token'] ?? null;
        $configuredToken = config('app.cron_token');

        if (!$configuredToken || $token !== $configuredToken) {
            http_response_code(403);
            echo 'Forbidden: Invalid or missing cron token.';
            return;
        }

        $tasks = $this->schedule->getDueTasks();
        $count = 0;

        foreach ($tasks as $task) {
            if ($cmd = $task->getCommand()) {
                $binary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
                $velvet = $this->app->basePath() . '/velvet';

                $command = build_cli_command($binary, $velvet, $cmd) . ' > /dev/null 2>&1 &';
                exec($command);
                $count++;
            } else {
                $task->run($this->app);
                $count++;
            }
        }

        echo "Ran {$count} scheduled tasks.";
    }
}
