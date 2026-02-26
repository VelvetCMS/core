<?php

declare(strict_types=1);

namespace VelvetCMS\Core\Tenancy;

final class TenantDiscovery
{
    /**
     * @return array<int, string>
     */
    public static function discoverTenantIds(): array
    {
        $config = TenancyManager::config();
        $userRoot = $config['paths']['user_root'] ?? 'user/tenants';
        $root = base_path(trim((string) $userRoot, '/'));

        if (!is_dir($root)) {
            return [];
        }

        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }

        $tenants = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!is_dir($root . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            $tenants[] = $entry;
        }

        sort($tenants);

        return array_values(array_unique($tenants));
    }
}
