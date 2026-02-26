<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Concerns;

use VelvetCMS\Core\Tenancy\ModuleArtifactPaths;
use VelvetCMS\Core\Tenancy\TenantDiscovery;

trait InteractsWithTenancy
{
    /**
     * @return array<int, string>
     */
    protected function resolveTenantSelection(bool $allowAllTenants = true, bool $fallbackToCurrentTenant = true): array
    {
        if (!tenant_enabled()) {
            throw new \RuntimeException('Tenancy must be enabled.');
        }

        $tenantOption = $this->option('tenant');
        $allTenants = (bool) $this->option('all-tenants', false);

        if (is_string($tenantOption) && $tenantOption !== '' && $allTenants) {
            throw new \RuntimeException('Use either --tenant or --all-tenants, not both.');
        }

        if (is_string($tenantOption) && $tenantOption !== '') {
            return [$tenantOption];
        }

        if ($allTenants) {
            if (!$allowAllTenants) {
                throw new \RuntimeException('--all-tenants is not supported by this command.');
            }

            return TenantDiscovery::discoverTenantIds();
        }

        if ($fallbackToCurrentTenant && tenant_id() !== null) {
            return [(string) tenant_id()];
        }

        return [];
    }

    protected function runVelvetSubcommand(string $basePath, string $command, ?string $tenantId = null): int
    {
        $cli = build_cli_command(PHP_BINARY, $basePath . '/velvet', $command);

        if ($tenantId !== null && $tenantId !== '') {
            $cli = 'TENANCY_TENANT=' . escapeshellarg($tenantId) . ' ' . $cli;
        }

        passthru($cli, $exitCode);

        return (int) $exitCode;
    }

    protected function resolveStatePathForRead(string $basePath): string
    {
        foreach (ModuleArtifactPaths::stateCandidates($basePath) as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return ModuleArtifactPaths::statePath(basePath: $basePath);
    }
}
