<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Concerns;

use ReflectionClass;

/**
 * Convenience wrappers for accessing private/protected members via reflection.
 */
trait ReflectionHelpers
{
    /**
     * Read a private or protected property value.
     *
     * @param object|class-string $target  Object instance or class name (for static properties).
     */
    protected function getPrivateProperty(object|string $target, string $property): mixed
    {
        $ref = new ReflectionClass($target);
        $prop = $ref->getProperty($property);

        return $prop->getValue(is_object($target) ? $target : null);
    }

    /**
     * Write a private or protected property value.
     *
     * @param object|class-string $target  Object instance or class name (for static properties).
     */
    protected function setPrivateProperty(object|string $target, string $property, mixed $value): void
    {
        $ref = new ReflectionClass($target);
        $prop = $ref->getProperty($property);
        $prop->setValue(is_object($target) ? $target : null, $value);
    }

    /**
     * Invoke a private or protected method and return its result.
     */
    protected function callPrivateMethod(object $target, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($target);
        $m = $ref->getMethod($method);

        return $m->invokeArgs($target, $args);
    }
}
