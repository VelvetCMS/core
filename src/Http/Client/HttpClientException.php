<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Client;

/** Thrown when the request itself fails (network error, DNS, timeout). */
final class HttpClientException extends \RuntimeException
{
}
