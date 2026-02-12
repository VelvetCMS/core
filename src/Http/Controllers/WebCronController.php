<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Controllers;

use VelvetCMS\Core\Application;
use VelvetCMS\Http\RateLimiting\RateLimiter;
use VelvetCMS\Scheduling\Schedule;

class WebCronController
{
    public function __construct(
        private readonly Application $app,
        private readonly Schedule $schedule
    ) {
    }

    public function run(): void
    {
        if (!$this->isIpAllowed()) {
            http_response_code(403);
            echo 'Forbidden: IP not allowed.';
            return;
        }

        if (!$this->isAuthorized()) {
            http_response_code(403);
            echo 'Forbidden: Invalid or missing cron token.';
            return;
        }

        if (!$this->passesRateLimit()) {
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

    private function isAuthorized(): bool
    {
        $configuredToken = (string) config('app.cron_token', '');
        if ($configuredToken === '') {
            return false;
        }

        $token = (string) ($_GET['token'] ?? '');
        if ($token !== '' && hash_equals($configuredToken, $token)) {
            return true;
        }

        if (!(bool) config('app.cron_signed_urls', false)) {
            return false;
        }

        $expires = (int) ($_GET['expires'] ?? 0);
        $signature = (string) ($_GET['signature'] ?? '');

        if ($expires <= 0 || $signature === '' || $expires < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', (string) $expires, $configuredToken);

        return hash_equals($expected, $signature);
    }

    private function isIpAllowed(): bool
    {
        $allowlist = config('app.cron_allowed_ips', []);
        if (!is_array($allowlist) || $allowlist === []) {
            return true;
        }

        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remoteIp === '') {
            return false;
        }

        foreach ($allowlist as $allowed) {
            if (!is_string($allowed) || $allowed === '') {
                continue;
            }

            if ($allowed === '*') {
                return true;
            }

            if (str_contains($allowed, '/')) {
                if ($this->ipInCidr($remoteIp, $allowed)) {
                    return true;
                }
                continue;
            }

            if ($allowed === $remoteIp) {
                return true;
            }
        }

        return false;
    }

    private function passesRateLimit(): bool
    {
        if (!(bool) config('app.cron_rate_limit.enabled', false)) {
            return true;
        }

        $attempts = max(1, (int) config('app.cron_rate_limit.attempts', 60));
        $decay = max(1, (int) config('app.cron_rate_limit.decay', 60));
        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        try {
            $limiter = $this->app->make(RateLimiter::class);
            if (!$limiter instanceof RateLimiter) {
                return true;
            }

            $result = $limiter->attempt('webcron:' . $remoteIp, $attempts, $decay);
            if ($result['allowed']) {
                return true;
            }

            http_response_code(429);
            echo 'Too Many Requests';

            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if (!is_string($subnet) || !is_string($bits) || !is_numeric($bits)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bitsInt = (int) $bits;
        $maxBits = strlen($ipBin) * 8;
        if ($bitsInt < 0 || $bitsInt > $maxBits) {
            return false;
        }

        $bytes = intdiv($bitsInt, 8);
        $remainingBits = $bitsInt % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (~(0xff >> $remainingBits)) & 0xff;
        $ipByte = ord($ipBin[$bytes]);
        $subnetByte = ord($subnetBin[$bytes]);

        return ($ipByte & $mask) === ($subnetByte & $mask);
    }
}
