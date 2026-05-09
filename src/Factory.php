<?php
declare(strict_types=1);

namespace Switon\Di;

use JsonSerializable;
use function array_keys;

/**
 * Expands named definitions for one service type and maps the plain type to <code>#default</code>.
 *
 * Guidance: Use this when one service type needs named variants plus one fallback slot.
 * Guidance: Keep <code>#default</code> intentional; the plain type resolves to that fallback slot.
 *
 * Road-signs:
 * - register <code>Type#name</code>
 * - map plain <code>Type</code> to the fallback slot
 * - register the factory before named lookups run
 * - property name selects the slot
 * - missing <code>default</code> fails when looked up
 *
 * @see \Switon\Di\FactoryInterface
 * @see \Switon\Di\NamedLookupInterface
 * @see \Switon\Di\Injector::resolveDependency()
 */
class Factory implements FactoryInterface, JsonSerializable
{
    /** @param array<string, mixed> $definitions Name => definition. */
    public function __construct(protected array $definitions = [])
    {

    }

    /**
     * Register all named definitions for one service type.
     *
     * Guidance: The plain type always points to the fallback slot.
     */
    public function register(string $type, ContainerInterface $container): void
    {
        foreach ($this->definitions as $name => $definition) {
            $container->set("$type#$name", $definition);
        }
        $container->set($type, '#default');
    }

    /** @return array<string, mixed> Named definitions keyed by slot name. */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /** @return list<string> Registered slot names. */
    public function getNames(): array
    {
        return array_keys($this->definitions);
    }

    /** @return array{definitions: array<string, mixed>} Serialized named definitions. */
    public function jsonSerialize(): array
    {
        return ['definitions' => $this->definitions];
    }
}
