<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Core\Tenancy;

use VelvetCMS\Core\Tenancy\ModuleArtifactPaths;
use VelvetCMS\Core\Tenancy\TenancyManager;
use VelvetCMS\Core\Tenancy\TenantContext;
use VelvetCMS\Tests\Support\Concerns\TenancyTestHelpers;
use VelvetCMS\Tests\Support\TestCase;

final class ModuleArtifactPathsTest extends TestCase
{
    use TenancyTestHelpers;
    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_uses_global_paths_when_tenancy_disabled(): void
    {
        $this->setTenancyConfig(['enabled' => false]);

        $this->assertSame(base_path('storage/modules.json'), ModuleArtifactPaths::statePath());
        $this->assertSame(base_path('storage/modules-compiled.json'), ModuleArtifactPaths::compiledPath());
        $this->assertSame(base_path('storage/modules-autoload.php'), ModuleArtifactPaths::autoloadPath());
    }

    public function test_uses_tenant_scoped_paths_when_enabled(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => [
                'storage_root' => 'storage/tenants',
            ],
        ]);

        TenancyManager::setCurrent(new TenantContext('tenant-a'));

        $this->assertSame(base_path('storage/tenants/tenant-a/modules/modules.json'), ModuleArtifactPaths::statePath());
        $this->assertSame(base_path('storage/tenants/tenant-a/modules/modules-compiled.json'), ModuleArtifactPaths::compiledPath());
        $this->assertSame(base_path('storage/tenants/tenant-a/modules/modules-autoload.php'), ModuleArtifactPaths::autoloadPath());
    }

    public function test_compiled_candidates_are_tenant_first_then_global(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => [
                'storage_root' => 'storage/tenants',
            ],
        ]);

        TenancyManager::setCurrent(new TenantContext('tenant-b'));

        $candidates = ModuleArtifactPaths::compiledCandidates();

        $this->assertSame(base_path('storage/tenants/tenant-b/modules/modules-compiled.json'), $candidates[0]);
        $this->assertSame(base_path('storage/modules-compiled.json'), $candidates[1]);
    }

}
