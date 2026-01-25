<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Core;

use InvalidArgumentException;
use VelvetCMS\Core\ModuleManifest;
use VelvetCMS\Tests\Support\TestCase;

final class ModuleManifestTest extends TestCase
{
    // === Constructor Validation ===

    public function test_constructor_requires_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not be empty');

        new ModuleManifest(
            name: '',
            version: '1.0.0',
            path: '/path/to/module',
            entry: 'MyModule\\Module',
            enabled: true,
        );
    }

    public function test_constructor_requires_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path must not be empty');

        new ModuleManifest(
            name: 'my-module',
            version: '1.0.0',
            path: '',
            entry: 'MyModule\\Module',
            enabled: true,
        );
    }

    public function test_constructor_requires_entry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entry must not be empty');

        new ModuleManifest(
            name: 'my-module',
            version: '1.0.0',
            path: '/path/to/module',
            entry: '',
            enabled: true,
        );
    }

    public function test_constructor_accepts_valid_manifest(): void
    {
        $manifest = new ModuleManifest(
            name: 'my-module',
            version: '1.0.0',
            path: '/modules/my-module',
            entry: 'MyModule\\Module',
            enabled: true,
            requires: ['core' => '^1.0'],
            conflicts: ['old-module'],
            provides: ['feature' => '1.0'],
            description: 'A test module',
            stability: 'stable',
            extra: ['custom' => 'value'],
        );

        $this->assertSame('my-module', $manifest->name);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertSame('/modules/my-module', $manifest->path);
        $this->assertSame('MyModule\\Module', $manifest->entry);
        $this->assertTrue($manifest->enabled);
        $this->assertSame(['core' => '^1.0'], $manifest->requires);
        $this->assertSame(['old-module'], $manifest->conflicts);
        $this->assertSame(['feature' => '1.0'], $manifest->provides);
        $this->assertSame('A test module', $manifest->description);
        $this->assertSame('stable', $manifest->stability);
        $this->assertSame(['custom' => 'value'], $manifest->extra);
    }

    // === fromArray Factory ===

    public function test_from_array_creates_manifest(): void
    {
        $manifest = ModuleManifest::fromArray('test-module', [
            'version' => '2.0.0',
            'path' => '/modules/test',
            'entry' => 'Test\\TestModule',
            'description' => 'Test description',
        ]);

        $this->assertSame('test-module', $manifest->name);
        $this->assertSame('2.0.0', $manifest->version);
        $this->assertSame('/modules/test', $manifest->path);
        $this->assertSame('Test\\TestModule', $manifest->entry);
        $this->assertSame('Test description', $manifest->description);
        $this->assertTrue($manifest->enabled);
    }

    public function test_from_array_uses_name_from_data_if_provided(): void
    {
        $manifest = ModuleManifest::fromArray('fallback-name', [
            'name' => 'actual-name',
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ]);

        $this->assertSame('actual-name', $manifest->name);
    }

    public function test_from_array_defaults_version_to_zero(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ]);

        $this->assertSame('0.0.0', $manifest->version);
    }

    public function test_from_array_respects_enabled_parameter(): void
    {
        $enabled = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ], enabled: true);

        $disabled = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ], enabled: false);

        $this->assertTrue($enabled->enabled);
        $this->assertFalse($disabled->enabled);
    }

    public function test_from_array_normalizes_requires(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'requires' => [
                'valid-dep' => '^1.0',
                '' => 'ignored',           // Empty key ignored
                'numeric-constraint' => 123, // Numeric converted to string
            ],
        ]);

        $this->assertSame([
            'valid-dep' => '^1.0',
            'numeric-constraint' => '123',
        ], $manifest->requires);
    }

    public function test_from_array_normalizes_conflicts(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'conflicts' => ['module-a', '', 'module-b', null, 'module-c'],
        ]);

        // Empty strings and nulls filtered out
        $this->assertSame(['module-a', 'module-b', 'module-c'], $manifest->conflicts);
    }

    public function test_from_array_handles_non_array_requires(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'requires' => 'not-an-array',
        ]);

        $this->assertSame([], $manifest->requires);
    }

    public function test_from_array_handles_non_array_conflicts(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'conflicts' => 'not-an-array',
        ]);

        $this->assertSame([], $manifest->conflicts);
    }

    public function test_from_array_captures_extra_fields(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'custom_field' => 'custom_value',
            'another_extra' => ['nested' => 'data'],
        ]);

        $this->assertSame([
            'custom_field' => 'custom_value',
            'another_extra' => ['nested' => 'data'],
        ], $manifest->extra);
    }

    // === toArray Round-trip ===

    public function test_to_array_includes_all_fields(): void
    {
        $manifest = new ModuleManifest(
            name: 'my-module',
            version: '1.2.3',
            path: '/modules/my-module',
            entry: 'MyModule\\Module',
            enabled: true,
            requires: ['core' => '^1.0'],
            conflicts: ['old-module'],
            provides: ['feature' => '1.0'],
            description: 'Description',
            stability: 'beta',
            extra: ['custom' => 'data'],
        );

        $array = $manifest->toArray();

        $this->assertSame('my-module', $array['name']);
        $this->assertSame('1.2.3', $array['version']);
        $this->assertSame('/modules/my-module', $array['path']);
        $this->assertSame('MyModule\\Module', $array['entry']);
        $this->assertTrue($array['enabled']);
        $this->assertSame(['core' => '^1.0'], $array['requires']);
        $this->assertSame(['old-module'], $array['conflicts']);
        $this->assertSame(['feature' => '1.0'], $array['provides']);
        $this->assertSame('Description', $array['description']);
        $this->assertSame('beta', $array['stability']);
        $this->assertSame('data', $array['custom']); // Extra merged in
    }

    public function test_to_array_round_trip(): void
    {
        $original = ModuleManifest::fromArray('test-module', [
            'version' => '1.0.0',
            'path' => '/modules/test',
            'entry' => 'Test\\Module',
            'requires' => ['core' => '^1.0'],
            'description' => 'Test',
        ]);

        $array = $original->toArray();
        $restored = ModuleManifest::fromArray('test-module', $array, $original->enabled);

        $this->assertSame($original->name, $restored->name);
        $this->assertSame($original->version, $restored->version);
        $this->assertSame($original->path, $restored->path);
        $this->assertSame($original->entry, $restored->entry);
        $this->assertSame($original->requires, $restored->requires);
        $this->assertSame($original->description, $restored->description);
    }

    // === Edge Cases ===

    public function test_nullable_fields_can_be_null(): void
    {
        $manifest = new ModuleManifest(
            name: 'test',
            version: '1.0.0',
            path: '/path',
            entry: 'Entry\\Class',
            enabled: true,
            description: null,
            stability: null,
        );

        $this->assertNull($manifest->description);
        $this->assertNull($manifest->stability);
    }

    public function test_empty_arrays_are_valid(): void
    {
        $manifest = new ModuleManifest(
            name: 'test',
            version: '1.0.0',
            path: '/path',
            entry: 'Entry\\Class',
            enabled: true,
            requires: [],
            conflicts: [],
            provides: [],
            extra: [],
        );

        $this->assertSame([], $manifest->requires);
        $this->assertSame([], $manifest->conflicts);
        $this->assertSame([], $manifest->provides);
        $this->assertSame([], $manifest->extra);
    }
}
