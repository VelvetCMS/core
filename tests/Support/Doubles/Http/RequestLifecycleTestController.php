<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Doubles\Http;

use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

final class RequestLifecycleTestController
{
    public function __construct(
        private readonly RequestLifecycleTestService $service
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html('<h1>' . $this->service->getMessage() . '</h1>');
    }
}
