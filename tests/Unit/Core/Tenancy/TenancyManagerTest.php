<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Core\Tenancy;

use ReflectionClass;
use VelvetCMS\Contracts\TenantResolverInterface;
use VelvetCMS\Core\Tenancy\TenantContext;
use VelvetCMS\Core\Tenancy\TenancyManager;
use VelvetCMS\Http\Request;
use VelvetCMS\Tests\Support\TestCase;

final class TenancyManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_bootstrap_from_request_disabled_returns_null(): void
    {
        $this->setTenancyConfig(['enabled' => false]);

        $request = $this->makeRequest('GET', '/');
        $context = TenancyManager::bootstrapFromRequest($request);

        $this->assertNull($context);
        $this->assertNull(TenancyManager::current());
    }

    public function test_host_mapping_resolves_tenant(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'resolver' => 'host',
            'host' => [
                'map' => [
                    'acme.test' => 'acme',
                ],
            ],
        ]);

        $request = $this->makeRequest('GET', '/', [], ['Host' => 'acme.test']);
        $context = TenancyManager::bootstrapFromRequest($request);

        $this->assertInstanceOf(TenantContext::class, $context);
        $this->assertSame('acme', $context->id());
        $this->assertSame('acme.test', $context->host());
        $this->assertSame('acme', TenancyManager::currentId());
    }

    public function test_path_resolver_sets_prefix_and_strips_request_path(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'resolver' => 'path',
            'path' => [
                'segment' => 1,
                'map' => [],
            ],
        ]);

        $request = $this->makeRequest('GET', '/tenant-x/docs');
        $context = TenancyManager::bootstrapFromRequest($request);

        $this->assertInstanceOf(TenantContext::class, $context);
        $this->assertSame('tenant-x', $context->id());
        $this->assertSame('/tenant-x', $context->pathPrefix());
        $this->assertSame('/docs', $request->path());
    }

    public function test_callback_resolver_uses_custom_resolver(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'resolver' => 'callback',
            'callback' => TestTenantResolver::class,
        ]);

        $request = $this->makeRequest('GET', '/');
        $context = TenancyManager::bootstrapFromRequest($request);

        $this->assertInstanceOf(TenantContext::class, $context);
        $this->assertSame('callback-tenant', $context->id());
        $this->assertSame(['source' => 'callback'], $context->metadata());
    }

    public function test_bootstrap_from_cli_uses_env_tenant(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'default' => 'default',
        ]);

        $_ENV['TENANCY_TENANT'] = 'cli-tenant';
        $_SERVER['TENANCY_TENANT'] = 'cli-tenant';
        putenv('TENANCY_TENANT=cli-tenant');

        try {
            $context = TenancyManager::bootstrapFromCli();
            $this->assertInstanceOf(TenantContext::class, $context);
            $this->assertSame('cli-tenant', $context->id());
        } finally {
            unset($_ENV['TENANCY_TENANT'], $_SERVER['TENANCY_TENANT']);
            putenv('TENANCY_TENANT');
        }
    }

    private function setTenancyConfig(array $config): void
    {
        $ref = new ReflectionClass(TenancyManager::class);

        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, $config);

        $currentProp = $ref->getProperty('current');
        $currentProp->setAccessible(true);
        $currentProp->setValue(null, null);
    }

    private function resetTenancyState(): void
    {
        $ref = new ReflectionClass(TenancyManager::class);

        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, null);

        $currentProp = $ref->getProperty('current');
        $currentProp->setAccessible(true);
        $currentProp->setValue(null, null);
    }
}

final class TestTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request, array $config): ?TenantContext
    {
        return new TenantContext('callback-tenant', null, null, null, ['source' => 'callback']);
    }
}
