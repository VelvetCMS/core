<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Services;

use RuntimeException;
use Psr\Log\LogLevel;
use VelvetCMS\Services\FileLogger;
use VelvetCMS\Tests\Support\TestCase;

final class FileLoggerTest extends TestCase
{
    public function test_respects_log_level_threshold(): void
    {
        $logPath = $this->tmpDir . '/logs/level.log';
        $logger = new FileLogger($logPath, LogLevel::WARNING);

        $logger->info('Informational');
        $logger->error('Something failed');

        $contents = file_get_contents($logPath) ?: '';

        $this->assertStringNotContainsString('INFO: Informational', $contents);
        $this->assertStringContainsString('ERROR: Something failed', $contents);
    }

    public function test_interpolates_context_and_formats_exception(): void
    {
        $logPath = $this->tmpDir . '/logs/context.log';
        $logger = new FileLogger($logPath, LogLevel::DEBUG);

        $exception = new RuntimeException('Boom');
        $logger->error('User {id} failed', ['id' => 42, 'exception' => $exception]);

        $contents = file_get_contents($logPath) ?: '';

        $this->assertStringContainsString('ERROR: User 42 failed', $contents);
        $this->assertStringContainsString('Exception:', $contents);
        $this->assertStringContainsString('RuntimeException', $contents);
    }
}
