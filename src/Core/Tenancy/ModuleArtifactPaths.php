<?php

declare(strict_types=1);

namespace VelvetCMS\Core\Tenancy;

final class ModuleArtifactPaths
{
    public static function statePath(?string $tenantId = null, ?string $basePath = null): string
    {
        return self::storageRoot($tenantId, $basePath) . '/modules.json';
    }

    public static function compiledPath(?string $tenantId = null, ?string $basePath = null): string
    {
        return self::storageRoot($tenantId, $basePath) . '/modules-compiled.json';
    }

    public static function autoloadPath(?string $tenantId = null, ?string $basePath = null): string
    {
        return self::storageRoot($tenantId, $basePath) . '/modules-autoload.php';
    }

    /**
     * @return array<int, string>
     */
    public static function compiledCandidates(?string $basePath = null): array
    {
        return self::tenantFirstCandidates('modules-compiled.json', $basePath);
    }

    /**
     * @return array<int, string>
     */
    public static function stateCandidates(?string $basePath = null): array
    {
        return self::tenantFirstCandidates('modules.json', $basePath);
    }

    /**
     * @return array<int, string>
     */
    public static function autoloadCandidates(?string $basePath = null): array
    {
        return self::tenantFirstCandidates('modules-autoload.php', $basePath);
    }

    public static function globalStatePath(?string $basePath = null): string
    {
        return self::basePath($basePath) . '/storage/modules.json';
    }

    public static function globalCompiledPath(?string $basePath = null): string
    {
        return self::basePath($basePath) . '/storage/modules-compiled.json';
    }

    public static function globalAutoloadPath(?string $basePath = null): string
    {
        return self::basePath($basePath) . '/storage/modules-autoload.php';
    }

    private static function storageRoot(?string $tenantId = null, ?string $basePath = null): string
    {
        if ($tenantId !== null && $tenantId !== '') {
            return self::tenantStorageRoot($tenantId, $basePath) . '/modules';
        }

        if (tenant_enabled() && tenant_id() !== null) {
            return self::tenantStorageRoot((string) tenant_id(), $basePath) . '/modules';
        }

        return self::basePath($basePath) . '/storage';
    }

    /**
     * @return array<int, string>
     */
    private static function tenantFirstCandidates(string $filename, ?string $basePath = null): array
    {
        $paths = [];

        if (tenant_enabled() && tenant_id() !== null) {
            $paths[] = self::tenantStorageRoot((string) tenant_id(), $basePath) . '/modules/' . $filename;
        }

        $paths[] = self::basePath($basePath) . '/storage/' . $filename;

        return array_values(array_unique($paths));
    }

    private static function tenantStorageRoot(string $tenantId, ?string $basePath = null): string
    {
        $config = TenancyManager::config();
        $root = $config['paths']['storage_root'] ?? 'storage/tenants';

        return self::basePath($basePath) . DIRECTORY_SEPARATOR . trim((string) $root, '/') . DIRECTORY_SEPARATOR . $tenantId;
    }

    private static function basePath(?string $basePath = null): string
    {
        return $basePath !== null && $basePath !== '' ? rtrim($basePath, '/\\') : base_path();
    }
}
