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
    
    /** Rate limiting */
    'rate_limit' => [
        'enabled' => true,
        'default' => 'standard',

        'limiters' => [
            'standard' => ['attempts' => 60, 'decay' => 60, 'by' => 'ip'],
            'api' => ['attempts' => 120, 'decay' => 60, 'by' => 'ip'],
            'auth' => ['attempts' => 5, 'decay' => 60, 'by' => 'ip'],
            'strict' => ['attempts' => 10, 'decay' => 60, 'by' => 'ip'],
        ],

        'whitelist' => ['127.0.0.1', '::1'],
    ],
];
