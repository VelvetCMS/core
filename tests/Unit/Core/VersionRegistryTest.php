<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Core;

use VelvetCMS\Core\VersionRegistry;
use VelvetCMS\Tests\Support\TestCase;

final class VersionRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton for each test
        $reflection = new \ReflectionClass(VersionRegistry::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setValue(null, null);

        // Set up test version config
        config(['version' => [
            'core' => [
                'version' => '1.5.0',
                'release_date' => '2025-01-15',
                'stability' => 'stable',
            ],
            'modules' => [
                'docs' => [
                    'version' => '1.0.0',
                    'requires' => [
                        'core' => '^1.0',
                        'php' => '^8.1',
                    ],
                ],
                'blog' => [
                    'version' => '2.0.0-beta',
                    'stability' => 'beta',
                    'requires' => [
                        'core' => '^1.5',
                    ],
                ],
                'legacy' => [
                    'version' => '0.5.0',
                    'requires' => [
                        'core' => '^2.0',
                    ],
                ],
            ],
        ]]);
    }

    public function test_instance_returns_singleton(): void
    {
        $instance1 = VersionRegistry::instance();
        $instance2 = VersionRegistry::instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_version_returns_core_version(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertSame('1.5.0', $registry->getVersion('core'));
    }

    public function test_get_version_returns_module_version(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertSame('1.0.0', $registry->getVersion('docs'));
        $this->assertSame('2.0.0-beta', $registry->getVersion('blog'));
    }

    public function test_get_version_returns_zero_for_unknown(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertSame('0.0.0', $registry->getVersion('nonexistent'));
    }

    public function test_get_release_date(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertSame('2025-01-15', $registry->getReleaseDate('core'));
        $this->assertNull($registry->getReleaseDate('docs'));
    }

    public function test_get_stability(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertSame('stable', $registry->getStability('core'));
        $this->assertSame('beta', $registry->getStability('blog'));
    }

    public function test_get_stability_infers_from_version(): void
    {
        $registry = VersionRegistry::instance();

        // docs has no explicit stability, should infer from version
        $stability = $registry->getStability('docs');
        $this->assertSame('stable', $stability);
    }

    public function test_get_component_returns_full_metadata(): void
    {
        $registry = VersionRegistry::instance();

        $core = $registry->getComponent('core');
        $this->assertArrayHasKey('version', $core);
        $this->assertArrayHasKey('release_date', $core);

        $docs = $registry->getComponent('docs');
        $this->assertArrayHasKey('version', $docs);
        $this->assertArrayHasKey('requires', $docs);
    }

    public function test_get_component_returns_empty_for_unknown(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertSame([], $registry->getComponent('unknown'));
    }

    public function test_get_modules(): void
    {
        $registry = VersionRegistry::instance();

        $modules = $registry->getModules();

        $this->assertArrayHasKey('docs', $modules);
        $this->assertArrayHasKey('blog', $modules);
        $this->assertArrayHasKey('legacy', $modules);
    }

    public function test_has_module(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertTrue($registry->hasModule('docs'));
        $this->assertFalse($registry->hasModule('nonexistent'));
    }

    public function test_satisfies_with_valid_constraint(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertTrue($registry->satisfies('1.5.0', '^1.0'));
        $this->assertTrue($registry->satisfies('1.5.0', '>=1.0'));
        $this->assertTrue($registry->satisfies('2.0.0', '^2.0'));
    }

    public function test_satisfies_with_invalid_constraint(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertFalse($registry->satisfies('1.5.0', '^2.0'));
        $this->assertFalse($registry->satisfies('0.5.0', '^1.0'));
    }

    public function test_satisfies_handles_invalid_constraint_syntax(): void
    {
        $registry = VersionRegistry::instance();

        // Invalid constraint should return false, not throw
        $this->assertFalse($registry->satisfies('1.0.0', 'not-a-constraint'));
    }

    public function test_is_compatible_with_core(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertTrue($registry->isCompatible('docs', 'core'));
        $this->assertTrue($registry->isCompatible('blog', 'core'));
        $this->assertFalse($registry->isCompatible('legacy', 'core')); // Requires ^2.0
    }

    public function test_is_compatible_with_custom_version(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertTrue($registry->isCompatible('legacy', 'core', '2.5.0'));
        $this->assertFalse($registry->isCompatible('legacy', 'core', '1.0.0'));
    }

    public function test_is_compatible_returns_false_for_unknown_module(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertFalse($registry->isCompatible('nonexistent', 'core'));
    }

    public function test_is_compatible_returns_true_when_no_requirement(): void
    {
        config(['version' => [
            'core' => ['version' => '1.0.0'],
            'modules' => [
                'no-deps' => ['version' => '1.0.0'],
            ],
        ]]);

        $reflection = new \ReflectionClass(VersionRegistry::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setValue(null, null);

        $registry = VersionRegistry::instance();

        $this->assertTrue($registry->isCompatible('no-deps', 'core'));
    }

    public function test_check_module_requirements_returns_empty_for_satisfied(): void
    {
        $registry = VersionRegistry::instance();

        $issues = $registry->checkModuleRequirements('docs');

        $this->assertSame([], $issues);
    }

    public function test_check_module_requirements_returns_issues_for_unsatisfied(): void
    {
        $registry = VersionRegistry::instance();

        $issues = $registry->checkModuleRequirements('legacy');

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('Core version', $issues[0]);
    }

    public function test_check_module_requirements_for_unknown_module(): void
    {
        $registry = VersionRegistry::instance();

        $issues = $registry->checkModuleRequirements('unknown');

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('not found', $issues[0]);
    }

    public function test_is_newer_than(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertTrue($registry->isNewerThan('1.0.0', 'core'));
        $this->assertFalse($registry->isNewerThan('2.0.0', 'core'));
        $this->assertFalse($registry->isNewerThan('1.5.0', 'core')); // Equal, not newer
    }

    public function test_is_pre_release(): void
    {
        $registry = VersionRegistry::instance();

        $this->assertFalse($registry->isPreRelease('core'));
        $this->assertTrue($registry->isPreRelease('blog')); // Has beta stability
    }
}
