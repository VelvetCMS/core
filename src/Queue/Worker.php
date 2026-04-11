<?php

declare(strict_types=1);

namespace VelvetCMS\Queue;

use VelvetCMS\Core\EventDispatcher;

final class Worker
{
    private bool $shouldQuit = false;

    public function __construct(
        private readonly QueueManager $manager,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    public function daemon(WorkerOptions $options): void
    {
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $jobsProcessed = 0;

        while (true) {
            if ($this->supportsAsyncSignals()) {
                pcntl_signal_dispatch();
            }

            if ($this->shouldQuit) {
                return;
            }

            $job = $this->manager->pop($options->queue);

            if ($job !== null) {
                $this->process($job, $options);
                $jobsProcessed++;

                if ($options->maxJobs > 0 && $jobsProcessed >= $options->maxJobs) {
                    return;
                }
            } else {
                sleep($options->sleep);
            }

            if ($this->memoryExceeded($options->memoryLimit)) {
                $this->stop();
            }
        }
    }

    public function process(Job $job, WorkerOptions $options): void
    {
        $this->dispatchEvent('queue.job.processing', $job);

        try {
            $job->handle();

            $this->manager->complete($job);
            $this->dispatchEvent('queue.job.processed', $job);
        } catch (\Throwable $e) {
            $this->handleException($job, $e);
        }
    }

    private function handleException(Job $job, \Throwable $e): void
    {
        if ($job->attempts >= $job->tries) {
            $this->manager->fail($job, $e);
            $this->dispatchEvent('queue.job.failed', ['job' => $job, 'exception' => $e]);
        } else {
            $this->manager->release($job, $job->retryAfter);
            $this->dispatchEvent('queue.job.released', $job);
        }
    }

    private function dispatchEvent(string $event, mixed $payload): void
    {
        $this->events?->dispatch($event, $payload);
    }

    private function supportsAsyncSignals(): bool
    {
        return extension_loaded('pcntl');
    }

    private function listenForSignals(): void
    {
        pcntl_signal(SIGTERM, fn () => $this->stop());
        pcntl_signal(SIGINT, fn () => $this->stop());
    }

    private function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    public function stop(): void
    {
        $this->shouldQuit = true;
    }
}
