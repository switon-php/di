<?php
declare(strict_types=1);

namespace Switon\Di;

use Switon\Core\Attribute\Autowired;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Resolves named services from container IDs like <code>Type#name</code>.
 *
 * Guidance: Use this entry when the code needs a named instance explicitly.
 * Guidance: Prefer <code>FactoryInterface</code> when the caller only needs registration, not discovery.
 *
 * Road-signs:
 * - explicit named lookup <code>by()</code>
 * - discover registered names <code>names()</code>
 * - factory expands <code>Type#name</code>
 * - plain <code>Type</code> still resolves the fallback slot
 * - property autowiring fallback lives in <code>Injector::resolveDependency()</code>
 *
 * @template T of object
 * @implements NamedLookupInterface<T>
 *
 * @see \Switon\Di\NamedLookupInterface
 * @see \Switon\Di\FactoryInterface
 * @see \Switon\Di\Factory
 * @see \Switon\Di\Injector::resolveDependency()
 * @see \Switon\Di\Container
 */
class NamedLookup implements NamedLookupInterface
{
    #[Autowired] protected ContainerInterface $container;

    /** {@inheritDoc} */
    public function by(string $type, string $name): object
    {
        return $this->container->get($type . "#$name");
    }

    /** {@inheritDoc} */
    public function names(string $type): array
    {
        $names = [];
        $prefix = $type . '#';
        foreach ($this->container->getDefinitions() as $serviceId => $definition) {
            if (str_starts_with($serviceId, $prefix)) {
                $names[] = substr($serviceId, strlen($prefix));
            }
        }

        return $names;
    }
}
