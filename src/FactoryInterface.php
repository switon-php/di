<?php
declare(strict_types=1);

namespace Switon\Di;

/**
 * Named-definition contract for one service type.
 *
 * Guidance: Use named definitions only when one service type needs multiple concrete instances plus a fallback slot.
 *
 * Road-signs:
 * - expand <code>Type#name</code>
 * - keep plain <code>Type</code> as the fallback slot
 * - property name selects the slot
 * - missing <code>default</code> fails at lookup time
 *
 * @see \Switon\Di\Factory
 * @see \Switon\Di\NamedLookupInterface
 * @see \Switon\Di\Injector::resolveDependency()
 */
interface FactoryInterface
{
    /**
     * Register named definitions for one service type.
     *
     * Guidance: Keep the plain type reserved for fallback lookup.
     */
    public function register(string $type, ContainerInterface $container): void;

    /** @return array<string, mixed> Name => definition mapping (class string, config array, reference, or object). */
    public function getDefinitions(): array;

    /** @return list<string> Registered names. */
    public function getNames(): array;
}
