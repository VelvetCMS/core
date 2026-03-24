<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Commands\Module;

use VelvetCMS\Commands\Module\ProvisionModuleCommand;
use VelvetCMS\Core\Tenancy\TenancyManager;
use VelvetCMS\Core\Tenancy\TenantContext;
use VelvetCMS\Tests\Support\Concerns\TenancyTestHelpers;
use VelvetCMS\Tests\Support\TestCase;

final class ProvisionModuleCommandTest extends TestCase
{
    use TenancyTestHelpers;
    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_returns_success_when_no_global_artifacts_exist(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => ['storage_root' => 'storage/tenants'],
        ]);
        TenancyManager::setCurrent(new TenantContext('demo'));

        // Temporarily move aside any real global artifacts so the command sees an empty state
        $globalFiles = [
            base_path('storage/modules.json'),
            base_path('storage/modules-compiled.json'),
            base_path('storage/modules-autoload.php'),
        ];

        $stashed = [];
        foreach ($globalFiles as $file) {
            if (file_exists($file)) {
                $backup = $file . '.test-backup';
                rename($file, $backup);
                $stashed[$file] = $backup;
            }
        }

        try {
            $command = new ProvisionModuleCommand();
            $command->setOptions(['tenant' => 'demo']);

            [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('No global module artifacts', $output);
        } finally {
            // Restore stashed files regardless of test outcome
            foreach ($stashed as $original => $backup) {
                rename($backup, $original);
            }
        }
    }

    public function test_dry_run_does_not_create_files(): void
    {
        $storageDir = base_path('storage');
        if (!is_dir($storageDir)) {
            $this->mkdir($storageDir);
        }

        file_put_contents($storageDir . '/modules.json', json_encode(['enabled' => ['mod-a']]));

        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => ['storage_root' => 'storage/tenants'],
        ]);
        TenancyManager::setCurrent(new TenantContext('dry-test'));

        $command = new ProvisionModuleCommand();
        $command->setOptions(['tenant' => 'dry-test', 'dry-run' => true]);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dry run complete', $output);

        $tenantState = base_path('storage/tenants/dry-test/modules/modules.json');
        $this->assertFileDoesNotExist($tenantState);

        @unlink($storageDir . '/modules.json');
    }

    public function test_migrates_state_with_merge(): void
    {
        $storageDir = base_path('storage');
        if (!is_dir($storageDir)) {
            $this->mkdir($storageDir);
        }

        file_put_contents($storageDir . '/modules.json', json_encode(['enabled' => ['mod-a', 'mod-b']]));

        $tenantModulesDir = base_path('storage/tenants/merge-test/modules');
        $this->mkdir($tenantModulesDir);
        file_put_contents($tenantModulesDir . '/modules.json', json_encode(['enabled' => ['mod-b', 'mod-c']]));

        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => ['storage_root' => 'storage/tenants'],
        ]);
        TenancyManager::setCurrent(new TenantContext('merge-test'));

        $command = new ProvisionModuleCommand();
        $command->setOptions(['tenant' => 'merge-test']);

        [$exitCode] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode);

        $result = json_decode(file_get_contents($tenantModulesDir . '/modules.json'), true);
        $this->assertEqualsCanonicalizing(['mod-a', 'mod-b', 'mod-c'], $result['enabled']);

        @unlink($storageDir . '/modules.json');
        $this->rrmdir(base_path('storage/tenants/merge-test'));
    }

    public function test_copies_compiled_when_missing(): void
    {
        $storageDir = base_path('storage');
        if (!is_dir($storageDir)) {
            $this->mkdir($storageDir);
        }

        $compiledData = json_encode(['timestamp' => '2026-02-24', 'modules' => []]);
        file_put_contents($storageDir . '/modules-compiled.json', $compiledData);

        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => ['storage_root' => 'storage/tenants'],
        ]);
        TenancyManager::setCurrent(new TenantContext('copy-test'));

        $command = new ProvisionModuleCommand();
        $command->setOptions(['tenant' => 'copy-test']);

        [$exitCode] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode);

        $tenantCompiled = base_path('storage/tenants/copy-test/modules/modules-compiled.json');
        $this->assertFileExists($tenantCompiled);
        $this->assertSame($compiledData, file_get_contents($tenantCompiled));

        @unlink($storageDir . '/modules-compiled.json');
        $this->rrmdir(base_path('storage/tenants/copy-test'));
    }

    public function test_skips_compiled_when_already_exists(): void
    {
        $storageDir = base_path('storage');
        if (!is_dir($storageDir)) {
            $this->mkdir($storageDir);
        }

        file_put_contents($storageDir . '/modules-compiled.json', '{"global": true}');

        $tenantModulesDir = base_path('storage/tenants/skip-test/modules');
        $this->mkdir($tenantModulesDir);
        file_put_contents($tenantModulesDir . '/modules-compiled.json', '{"tenant": true}');

        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => ['storage_root' => 'storage/tenants'],
        ]);
        TenancyManager::setCurrent(new TenantContext('skip-test'));

        $command = new ProvisionModuleCommand();
        $command->setOptions(['tenant' => 'skip-test']);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('skip compiled manifest (exists)', $output);

        // Verify tenant file was NOT overwritten
        $content = json_decode(file_get_contents($tenantModulesDir . '/modules-compiled.json'), true);
        $this->assertTrue($content['tenant']);

        @unlink($storageDir . '/modules-compiled.json');
        $this->rrmdir(base_path('storage/tenants/skip-test'));
    }

}
