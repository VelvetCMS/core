<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Concerns;

use ReflectionClass;
use VelvetCMS\Core\Tenancy\TenancyManager;
use VelvetCMS\Core\Tenancy\TenantContext;

trait TenancyTestHelpers
{
    protected function setTenancyConfig(array $config): void
    {
        $ref = new ReflectionClass(TenancyManager::class);

        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, $config);

        $currentProp = $ref->getProperty('current');
        $currentProp->setAccessible(true);
        $currentProp->setValue(null, null);
    }

    protected function resetTenancyState(): void
    {
        $ref = new ReflectionClass(TenancyManager::class);

        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, null);

        $currentProp = $ref->getProperty('current');
        $currentProp->setAccessible(true);
        $currentProp->setValue(null, null);
    }

    protected function setCurrentTenant(string $id): void
    {
        TenancyManager::setCurrent(new TenantContext($id));
    }
}
