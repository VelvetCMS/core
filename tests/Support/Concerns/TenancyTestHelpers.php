<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Concerns;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\Tenancy\TenancyState;
use VelvetCMS\Core\Tenancy\TenantContext;

trait TenancyTestHelpers
{
    protected function setTenancyConfig(array $config): void
    {
        $this->tenancyState()->setConfig($config);
    }

    protected function resetTenancyState(): void
    {
        $state = $this->tenancyState();
        $state->setConfig([]);
        $state->setCurrent(null);
    }

    protected function setCurrentTenant(string $id): void
    {
        $this->tenancyState()->setCurrent(new TenantContext($id));
    }

    private function tenancyState(): TenancyState
    {
        /** @var TenancyState $state */
        $state = Application::getInstance()->make(TenancyState::class);

        return $state;
    }
}
