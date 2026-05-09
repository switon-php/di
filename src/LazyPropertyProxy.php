<?php

declare(strict_types=1);

namespace Switon\Di;

use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Switon\Core\Lazy;
use function call_user_func_array;
use function str_starts_with;

/**
 * Defers property service resolution for <code>Type|Lazy</code> injections.
 *
 * @see \Switon\Core\Lazy
 * @see \Switon\Core\Attribute\Autowired
 * @see \Switon\Di\Injector
 */
class LazyPropertyProxy implements Lazy
{
    protected ContainerInterface $container;
    protected ReflectionProperty $property;
    protected object $object;
    protected string $type;
    protected ?string $value = null;

    /**
     * @param string $type Concrete or interface type to resolve.
     * @param string|null $value Optional explicit service ID from config or make() parameters; relative IDs like <code>#cache</code> resolve within <code>$type</code>.
     */
    public function __construct(
        ContainerInterface $container,
        ReflectionProperty $property,
        object             $object,
        string             $type,
        ?string            $value
    )
    {
        $this->container = $container;
        $this->property = $property;
        $this->object = $object;
        $this->type = $type;
        $this->value = $value;
    }

    /** Resolves the target service and swaps proxy property to the real instance. */
    protected function resolve(): object
    {
        if ($this->value !== null) {
            $serviceId = str_starts_with($this->value, '#') ? $this->type . $this->value : $this->value;
            $value = $this->container->get($serviceId);
        } else {
            $alias = $this->type . '#' . $this->property->getName();
            $value = $this->container->has($alias)
                ? $this->container->get($alias)
                : $this->container->get($this->type);
        }

        $this->property->setValue($this->object, $value);

        return $value;
    }

    /** Resolves service on first property access. */
    public function __get(string $name): mixed
    {
        $service = $this->resolve();
        if (property_exists($service, $name)) {
            return $service->$name;
        }
        $serviceClass = get_class($service);
        throw new \Error("Undefined property: {$serviceClass}::\${$name}");
    }

    /** Resolves service on first method call, then forwards the call to the resolved instance. */
    public function __call(string $name, array $args): mixed
    {
        $service = $this->resolve();
        return call_user_func_array([$service, $name], $args);
    }
}
