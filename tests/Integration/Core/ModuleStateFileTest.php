<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Core;

use VelvetCMS\Tests\Support\ApplicationTestCase;
use VelvetCMS\Tests\Support\Concerns\WritesTestFiles;

final class ModuleStateFileTest extends ApplicationTestCase
{
    use WritesTestFiles;

    public function test_state_file_can_enable_module(): void
    {
        $statePath = $this->sandboxPath('storage/modules.json');

        $this->writeJsonFile($statePath, ['enabled' => ['toggle-module']]);

        $loaded = $this->readJsonFile($statePath);

        $this->assertSame(['toggle-module'], $loaded['enabled']);
    }

    public function test_state_file_can_disable_module(): void
    {
        $statePath = $this->sandboxPath('storage/modules.json');

        $this->writeJsonFile($statePath, ['enabled' => ['toggle-module']]);
        $this->writeJsonFile($statePath, ['enabled' => []]);

        $loaded = $this->readJsonFile($statePath);

        $this->assertSame([], $loaded['enabled']);
    }
}
