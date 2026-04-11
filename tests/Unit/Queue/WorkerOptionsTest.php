<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Queue;

use VelvetCMS\Queue\WorkerOptions;
use VelvetCMS\Tests\Support\TestCase;

final class WorkerOptionsTest extends TestCase
{
    public function test_defaults(): void
    {
        $options = new WorkerOptions();

        $this->assertSame('default', $options->queue);
        $this->assertSame(3, $options->sleep);
        $this->assertSame(128, $options->memoryLimit);
        $this->assertSame(0, $options->maxJobs);
        $this->assertSame(60, $options->timeout);
        $this->assertSame(90, $options->retryAfter);
    }

    public function test_custom_values(): void
    {
        $options = new WorkerOptions(
            queue: 'emails',
            sleep: 5,
            memoryLimit: 256,
            maxJobs: 100,
            timeout: 120,
            retryAfter: 180,
        );

        $this->assertSame('emails', $options->queue);
        $this->assertSame(5, $options->sleep);
        $this->assertSame(256, $options->memoryLimit);
        $this->assertSame(100, $options->maxJobs);
        $this->assertSame(120, $options->timeout);
        $this->assertSame(180, $options->retryAfter);
    }
}
