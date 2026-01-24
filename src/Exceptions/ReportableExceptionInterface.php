<?php

declare(strict_types=1);

namespace VelvetCMS\Exceptions;

use Psr\Log\LoggerInterface;

interface ReportableExceptionInterface
{
    public function report(LoggerInterface $logger): void;
}
