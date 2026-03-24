<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

/**
 * Typed module manifest data.
 *
 * This is intentionally a small value object:
 * - validates and normalizes shape
 * - can round-trip to array for backwards compatibility
 */
final class ModuleManifest
{
    /**
     * @param array<string, string> $requires
     * @param string[] $conflicts
     * @param array<string, mixed> $provides
     * @param array<string, string> $commands  Signature → FQCN
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $path,
        public readonly string $entry,
        public readonly bool $enabled,
        public readonly array $requires = [],
        public readonly array $conflicts = [],
        public readonly array $provides = [],
        public readonly array $commands = [],
        public readonly ?string $description = null,
        public readonly ?string $stability = null,
        public readonly array $extra = [],
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Module manifest: name must not be empty');
        }

        if ($this->path === '') {
            throw new \InvalidArgumentException('Module manifest: path must not be empty');
        }

        if ($this->entry === '') {
            throw new \InvalidArgumentException("Module manifest '{$this->name}': entry must not be empty");
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $name, array $data, bool $enabled = true): self
    {
        $manifestName = (string) ($data['name'] ?? $name);

        $version = (string) ($data['version'] ?? '0.0.0');
        $path = (string) ($data['path'] ?? '');
        $entry = (string) ($data['entry'] ?? '');

        $requires = is_array($data['requires'] ?? null) ? $data['requires'] : [];
        $conflicts = is_array($data['conflicts'] ?? null) ? array_values($data['conflicts']) : [];
        $provides = is_array($data['provides'] ?? null) ? $data['provides'] : [];

        $commands = is_array($data['commands'] ?? null) ? self::normalizeCommands($data['commands']) : [];

        $description = isset($data['description']) ? (string) $data['description'] : null;
        $stability = isset($data['stability']) ? (string) $data['stability'] : null;

        $known = [
            'name' => true,
            'version' => true,
            'path' => true,
            'entry' => true,
            'enabled' => true,
            'requires' => true,
            'conflicts' => true,
            'provides' => true,
            'commands' => true,
            'description' => true,
            'stability' => true,
        ];

        $extra = [];
        foreach ($data as $key => $value) {
            if (!isset($known[(string) $key])) {
                $extra[(string) $key] = $value;
            }
        }

        return new self(
            name: $manifestName,
            version: $version,
            path: $path,
            entry: $entry,
            enabled: $enabled,
            requires: self::normalizeRequires($requires),
            conflicts: self::normalizeStringList($conflicts),
            provides: $provides,
            commands: $commands,
            description: $description,
            stability: $stability,
            extra: $extra,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge([
            'name' => $this->name,
            'version' => $this->version,
            'path' => $this->path,
            'entry' => $this->entry,
            'enabled' => $this->enabled,
            'requires' => $this->requires,
            'conflicts' => $this->conflicts,
            'provides' => $this->provides,
            'commands' => $this->commands,
            'description' => $this->description,
            'stability' => $this->stability,
        ], $this->extra);
    }

    /** @param array<mixed, mixed> $requires */
    private static function normalizeRequires(array $requires): array
    {
        $normalized = [];

        foreach ($requires as $dep => $constraint) {
            if (!is_string($dep) || $dep === '') {
                continue;
            }
            $normalized[$dep] = is_scalar($constraint) ? (string) $constraint : '';
        }

        return $normalized;
    }

    /** @param array<mixed> $items */
    private static function normalizeStringList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }

    /** @param array<mixed, mixed> $commands */
    private static function normalizeCommands(array $commands): array
    {
        $normalized = [];

        foreach ($commands as $signature => $class) {
            if (!is_string($signature) || $signature === '' || !is_string($class) || $class === '') {
                continue;
            }
            $normalized[$signature] = $class;
        }

        return $normalized;
    }
}
