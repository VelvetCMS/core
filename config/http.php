<?php

declare(strict_types=1);

/**
 * HTTP Configuration
 */
return [
    /** Middleware configuration */
    'middleware' => [
        /** Middleware aliases */
        'aliases' => [
            'errors' => \VelvetCMS\Http\Middleware\ErrorHandlingMiddleware::class,
            'throttle' => \VelvetCMS\Http\Middleware\ThrottleRequests::class,
            'csrf' => \VelvetCMS\Http\Middleware\VerifyCsrfToken::class,
            'session' => \VelvetCMS\Http\Middleware\StartSessionMiddleware::class,
        ],
        
        /** Global middleware stack */
        'global' => [
            'errors',
            'session',
            'throttle',
        ],
    ],
    
    /** Rate limiting configuration */
    'rate_limit' => [
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],
];
