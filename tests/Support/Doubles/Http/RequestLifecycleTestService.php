<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Doubles\Http;

final class RequestLifecycleTestService
{
    public function getMessage(): string
    {
        return 'Service injected!';
    }
}
