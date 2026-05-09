<?php
declare(strict_types=1);

namespace Switon\Di;

/**
 * Explicit lookup contract for <code>Type#name</code> services.
 *
 * Guidance: Use this only when the caller must choose a named instance directly.
 *
 * Road-signs:
 * - <code>by()</code> resolves one named service
 * - <code>names()</code> lists registered names
 * - plain <code>Type</code> remains the fallback slot
 *
 * @template T of object
 * @see \Switon\Di\NamedLookup
 * @see \Switon\Di\FactoryInterface
 * @see \Switon\Di\Container
 */
interface NamedLookupInterface
{
    /**
     * @param class-string<T> $type
     * @return T
     */
    public function by(string $type, string $name): object;

    /**
     * @param class-string<T> $type
     * @return list<string>
     */
    public function names(string $type): array;
}
