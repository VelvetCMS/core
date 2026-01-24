<?php

declare(strict_types=1);

namespace VelvetCMS\Core\Tenancy;

use VelvetCMS\Contracts\TenantResolverInterface;
use VelvetCMS\Http\Request;

final class TenancyManager
{
    private static ?TenantContext $current = null;
    private static ?array $config = null;

    public static function bootstrapFromRequest(Request $request): ?TenantContext
    {
        $config = self::config();
        if (!(bool) ($config['enabled'] ?? false)) {
            return null;
        }

        $context = self::resolveFromRequest($request, $config);
        if ($context === null) {
            $context = new TenantContext(self::defaultId($config));
        }

        self::setCurrent($context);

        if ($context->pathPrefix() !== null) {
            $request->setPathPrefix($context->pathPrefix());
        }

        return $context;
    }

    public static function bootstrapFromCli(): ?TenantContext
    {
        $config = self::config();
        if (!(bool) ($config['enabled'] ?? false)) {
            return null;
        }

        $tenantId = env('TENANCY_TENANT', env('TENANT', null));
        if (!is_string($tenantId) || $tenantId === '') {
            $tenantId = self::defaultId($config);
        }

        $context = new TenantContext($tenantId);
        self::setCurrent($context);
        return $context;
    }

    public static function setCurrent(TenantContext $context): void
    {
        self::$current = $context;
    }

    public static function current(): ?TenantContext
    {
        return self::$current;
    }

    public static function currentId(): ?string
    {
        return self::$current?->id();
    }

    public static function isEnabled(): bool
    {
        $config = self::config();
        return (bool) ($config['enabled'] ?? false);
    }

    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $configFile = config_path('tenancy.php');
        if (!file_exists($configFile)) {
            self::$config = [];
            return self::$config;
        }

        $config = require $configFile;
        self::$config = is_array($config) ? $config : [];

        return self::$config;
    }

    public static function defaultId(?array $config = null): string
    {
        $config ??= self::config();
        $default = $config['default'] ?? 'default';
        return is_string($default) && $default !== '' ? $default : 'default';
    }

    private static function resolveFromRequest(Request $request, array $config): ?TenantContext
    {
        $resolver = $config['resolver'] ?? 'host';

        if ($resolver === 'callback') {
            return self::resolveFromCallback($request, $config);
        }

        if ($resolver === 'path') {
            return self::resolveFromPath($request, $config);
        }

        return self::resolveFromHost($request, $config);
    }

    private static function resolveFromCallback(Request $request, array $config): ?TenantContext
    {
        $resolverClass = $config['callback'] ?? null;
        if (!is_string($resolverClass) || $resolverClass === '' || !class_exists($resolverClass)) {
            return null;
        }

        $resolver = new $resolverClass();
        if (!$resolver instanceof TenantResolverInterface) {
            return null;
        }

        return $resolver->resolve($request, $config);
    }

    private static function resolveFromHost(Request $request, array $config): ?TenantContext
    {
        $hostConfig = $config['host'] ?? [];
        $host = strtolower($request->host());

        if ($host === '') {
            return null;
        }

        if (!empty($hostConfig['strip_www'])) {
            $host = preg_replace('/^www\./', '', $host) ?? $host;
        }

        $map = $hostConfig['map'] ?? [];
        if (is_array($map) && isset($map[$host])) {
            return new TenantContext((string) $map[$host], $host);
        }

        if (!empty($hostConfig['wildcard_subdomains'])) {
            $rootDomains = array_filter((array) ($hostConfig['root_domains'] ?? []));
            foreach ($rootDomains as $rootDomain) {
                $rootDomain = strtolower((string) $rootDomain);
                if ($rootDomain === '') {
                    continue;
                }
                $suffix = '.' . $rootDomain;
                if (str_ends_with($host, $suffix)) {
                    $subdomain = substr($host, 0, -strlen($suffix));
                    if ($subdomain !== '' && $subdomain !== $host) {
                        return new TenantContext($subdomain, $host);
                    }
                }
            }
        }

        return null;
    }

    private static function resolveFromPath(Request $request, array $config): ?TenantContext
    {
        $pathConfig = $config['path'] ?? [];
        $segmentIndex = (int) ($pathConfig['segment'] ?? 1);
        $segmentIndex = max(1, $segmentIndex) - 1;

        $path = trim($request->rawPath(), '/');
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        if (!isset($segments[$segmentIndex])) {
            return null;
        }

        $segment = $segments[$segmentIndex];
        if ($segment === '') {
            return null;
        }

        $map = $pathConfig['map'] ?? [];
        $tenantId = is_array($map) && isset($map[$segment]) ? (string) $map[$segment] : $segment;

        $prefixSegments = array_slice($segments, 0, $segmentIndex + 1);
        $prefix = '/' . implode('/', $prefixSegments);

        return new TenantContext($tenantId, null, $prefix, $prefix);
    }
}
