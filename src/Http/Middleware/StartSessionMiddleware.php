<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Middleware;

use VelvetCMS\Contracts\MiddlewareInterface;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

class StartSessionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            $this->configure();
            session_start();
        }

        return $next($request);
    }

    private function configure(): void
    {
        // Session lifetime
        $lifetime = config('session.lifetime', 7200);
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_lifetime', (string) $lifetime);

        // Custom session name
        $sessionName = config('session.name', 'velvet_session');
        session_name($sessionName);

        // Security settings
        ini_set('session.cookie_httponly', config('session.http_only', true) ? '1' : '0');
        ini_set('session.use_strict_mode', config('session.strict_mode', true) ? '1' : '0');
        ini_set('session.use_only_cookies', config('session.use_only_cookies', true) ? '1' : '0');

        // Secure flag - auto-detect HTTPS or read from config
        $secure = config('session.secure', 'auto');
        if ($secure === 'auto') {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $secure = $isHttps || config('app.env') === 'production';
        }
        ini_set('session.cookie_secure', $secure ? '1' : '0');

        // SameSite attribute
        $sameSite = config('session.same_site', 'Lax');
        ini_set('session.cookie_samesite', $sameSite);

        // Session path and domain
        $path = config('session.path');
        if (!$path && tenant_prefix() !== '') {
            $path = tenant_prefix();
        }
        if ($path) {
            ini_set('session.cookie_path', $path);
        }
        if ($domain = config('session.domain')) {
            ini_set('session.cookie_domain', $domain);
        }
    }
}
