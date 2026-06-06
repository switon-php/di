<?php

declare(strict_types=1);

namespace Switon\Di;

/**
 * Core container contract plus mutation and inspection hooks.
 *
 * Guidance: Use this only when code needs definition mutation, inspection, or explicit container-side overrides.
 *
 * Road-signs:
 * - mutate definitions with <code>set()</code>, <code>replace()</code>, <code>remove()</code>
 * - inspect definitions with <code>getDefinition()</code> and <code>getDefinitions()</code>
 * - inspect resolved singletons with <code>getInstances()</code>
 * - interface auto-maps and named services are resolved by convention
 *
 * @see \Switon\Core\ContainerInterface
 * @see \Switon\Di\Container
 */
interface ContainerInterface extends \Switon\Core\ContainerInterface
{
    /**
     * Remove a definition and its cached singleton instance.
     */
    public function remove(string $id): static;

    /**
     * Replace a definition after clearing its cached singleton.
     *
     * Existing consumers keep the old instance.
     */
    public function replace(string $id, mixed $definition): static;

    /**
     * Return whether the container can resolve the ID from definitions, cached instances, or auto-resolution rules.
     */
    public function has(string $id): bool;

    /**
     * Return the raw definition as registered, without instantiating the service.
     */
    public function getDefinition(string $id): mixed;

    /**
     * @return array<string, mixed> All registered definitions keyed by service ID.
     */
    public function getDefinitions(): array;

    /**
     * @return array<string, mixed> All resolved singleton instances keyed by service ID.
     */
    public function getInstances(): array;
}
