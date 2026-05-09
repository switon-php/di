<?php

declare(strict_types=1);

namespace Switon\Di;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Switon\Core\ClassName;
use Switon\Core\Exception\MisuseException;
use Switon\Core\InjectorInterface;
use Switon\Core\Strings;
use Switon\Di\Event\SingletonCreated;
use Switon\Di\Exception\CircularDependencyException;
use Switon\Di\Exception\InterfaceAutowiringException;
use Switon\Di\Exception\NotFoundException;
use Switon\Di\Exception\RedundantAutoMappingException;
use Switon\Di\Exception\ReflectionException;
use Switon\Di\Exception\ServiceAlreadyResolvedException;
use Switon\Di\Exception\UnsupportedDefinitionException;
use function class_exists;
use function class_implements;
use function explode;
use function gettype;
use function interface_exists;
use function is_a;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strpos;

/**
 * Resolves, creates, and caches DI services from explicit definitions and auto-mapping rules.
 *
 * Guidance: Same-namespace <code>*Interface</code> → <code>*</code> is auto-mapped; use <code>set()</code> for overrides, aliases, factories, and cross-namespace bindings.
 * Guidance: Use <code>get()</code> for shared services and <code>make()</code> for fresh instances.
 *
 * Road-signs:
 * - resolve <code>get()</code>
 * - register <code>set()</code> / <code>replace()</code> / <code>remove()</code>
 * - same-namespace <code>*Interface -> *</code> auto-map
 * - interface IDs can own singleton instances
 * - new instance <code>make()</code>
 * - named definitions via <code>FactoryInterface</code>
 * - property injection <code>Injector</code>
 *
 * @see \Switon\Di\ContainerInterface
 * @see \Switon\Di\Injector
 * @see \Switon\Invoking\Invoker
 * @see \Switon\Di\FactoryInterface
 * @see \Switon\Di\Event\SingletonCreated
 * @see \Switon\Di\Exception\NotFoundException
 */
class Container implements ContainerInterface
{
    /** Suffix used for interface-to-class auto-mapping. */
    protected const string INTERFACE_SUFFIX = 'Interface';

    /** Cached length of <code>INTERFACE_SUFFIX</code>. */
    protected const int INTERFACE_SUFFIX_LENGTH = 9;

    protected InjectorInterface $injector;

    protected InvokerInterface $invoker;

    /** @var array<string, mixed> Registered definitions keyed by service ID. */
    protected array $definitions = [];

    /** @var array<string, mixed> Resolved singleton instances keyed by service ID. */
    protected array $instances = [];

    /** @param array<string, mixed> $definitions Initial service definitions. */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions;

        $this->instances[static::class] = $this;
        $this->registerSelfInterfaces();
        // Core services (InjectorInterface, InvokerInterface) are wired by
        // the DI ServiceProvider and lazily resolved when first needed.
    }

    /** Registers this container instance under every interface it implements. */
    protected function registerSelfInterfaces(): void
    {
        foreach (class_implements(static::class) as $interface) {
            if (!isset($this->definitions[$interface])) {
                $this->definitions[$interface] = $this;
            }
        }
    }

    /** Dispatches a DI lifecycle event when an event dispatcher is available. */
    protected function dispatchEvent(object $event): void
    {
        $dispatcher = $this->instances[EventDispatcherInterface::class] ?? null;
        if ($dispatcher !== null) {
            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher->dispatch($event);
        }
    }

    /**
     * Registers one service definition.
     *
     * Supports class names, instances, config arrays, <code>FactoryInterface</code>, and reference IDs.
     * Named variants should use <code>Type#name</code> IDs or a factory definition.
     *
     * @param mixed $definition Class name, instance, array config, <code>FactoryInterface</code>, or reference ID.
     * @throws ServiceAlreadyResolvedException
     * @see \Switon\Di\FactoryInterface::register()
     * @see \Switon\Di\Injector::resolveDependency()
     */
    public function set(string $id, mixed $definition): static
    {
        if (isset($this->instances[$id])) {
            ServiceAlreadyResolvedException::raise('Cannot set service "{id}": already resolved. Use replace() to override, or set() before first get()', ['id' => $id]);
        }

        // Fail-fast: do not register auto-mappable same-namespace Interface → Class bindings.
        // This is a common mistake and makes containers noisy.
        if (ClassName::isAutoMapPair($id, $definition)) {
            RedundantAutoMappingException::raise(
                'Redundant container definition: "{id}" → "{class}" is auto-mapped by convention. Remove this set() call.',
                ['id' => $id, 'class' => $definition]
            );
        }

        // YAML / array config sugar: when definition is an array with an explicit FactoryInterface class,
        // treat remaining keys as named definitions and expand them via FactoryInterface::register().
        if (is_array($definition) && isset($definition['class']) && is_string($definition['class']) && is_a($definition['class'], FactoryInterface::class, true)) {
            $class = $definition['class'];
            unset($definition['class']);
            $definition = new $class($definition);
        }

        if ($definition instanceof FactoryInterface) {
            $this->instances[FactoryInterface::class . "#$id"] = $definition;
            $definition->register($id, $this);
        } else {
            if (is_array($definition)) {
                $d = $this->definitions[$id] ?? null;
                if (!isset($definition['class'])) {
                    if (is_string($d)) {
                        $definition['class'] = $d;
                    } elseif (is_array($d) && is_string($d['class'])) {
                        $definition['class'] = $d['class'];
                    }
                }
            } elseif (is_string($definition)) {
                $d = $this->definitions[$id] ?? null;
                if (is_array($d) && !isset($d['class'])) {
                    $d['class'] = $definition;
                    $definition = $d;
                }
            }
            $this->definitions[$id] = $definition;
        }

        return $this;
    }

    /**
     * Replaces a definition even if the singleton was already resolved.
     *
     * Existing objects keep references to the old instance.
     * Use this for explicit override paths; it is not a mutation shortcut for unresolved defaults.
     */
    public function replace(string $id, mixed $definition): static
    {
        $this->remove($id);

        // Pre-check: when replacement matches auto-map convention, keep it as no-op after remove().
        if (ClassName::isAutoMapPair($id, $definition)) {
            return $this;
        }

        return $this->set($id, $definition);
    }

    /**
     * Removes a definition, its singleton instance, and its factory registration cache.
     *
     * For factory-based services, this removes only the main ID and cached factory object.
     * Use this to clear a binding before re-registering it.
     */
    public function remove(string $id): static
    {
        // Remove main service definition and instance
        unset($this->definitions[$id], $this->instances[$id]);

        // Remove associated FactoryInterface instance if exists
        unset($this->instances[FactoryInterface::class . "#$id"]);

        return $this;
    }

    /**
     * Creates one object instance and runs property injection before constructor invocation.
     *
     * @param array<string, mixed> $parameters Constructor arguments and property overrides.
     * @throws ReflectionException
     * @internal
     */
    protected function createInstance(string $className, array $parameters = [], ?string $id = null): object
    {
        try {
            $rClass = new ReflectionClass($className);
        } catch (\ReflectionException $e) {
            ReflectionException::raise('Cannot reflect class "{class}": {message}',
                ['class' => $className, 'message' => $e->getMessage()]);
        }

        $success = false;
        if (method_exists($className, '__construct')) {
            try {
                $instance = $rClass->newInstanceWithoutConstructor();
            } catch (\ReflectionException $e) {
                ReflectionException::raise('Cannot instantiate class "{class}" without constructor: {message}',
                    ['class' => $className, 'message' => $e->getMessage()],
                );
            }

            // Cache instance before property injection to support circular dependencies
            if ($id !== null) {
                $this->instances[$id] = $instance;
            }

            try {
                if (!isset($this->injector)) {
                    $this->injector = $this->get(InjectorInterface::class);
                }
                $this->injector->inject($instance, $parameters, $rClass);

                if (!isset($this->invoker)) {
                    $this->invoker = $this->get(InvokerInterface::class);
                }
                $this->invoker->invoke([$instance, '__construct'], $parameters);

                $success = true;
            } finally {
                // If initialization fails, remove cached instance to prevent returning partially initialized object
                if (!$success && $id !== null) {
                    unset($this->instances[$id]);
                }
            }
        } else {
            $instance = new $className();

            // Cache instance before property injection to support circular dependencies
            if ($id !== null) {
                $this->instances[$id] = $instance;
            }

            try {
                if (!isset($this->injector)) {
                    $this->injector = $this->get(InjectorInterface::class);
                }
                $this->injector->inject($instance, $parameters, $rClass);

                $success = true;
            } finally {
                // If initialization fails, remove cached instance to prevent returning partially initialized object
                if (!$success && $id !== null) {
                    unset($this->instances[$id]);
                }
            }
        }

        return $instance;
    }

    /**
     * Creates an instance from one normalized definition value.
     *
     * @throws UnsupportedDefinitionException
     * @throws MisuseException
     * @throws CircularDependencyException
     * @throws NotFoundException
     */
    protected function createByDefinition(mixed $definition, ?string $id = null): object
    {
        // Handle object definition
        if (is_object($definition)) {
            if (method_exists($definition, '__invoke')) {
                // Object with __invoke - call it
                if (!isset($this->invoker)) {
                    $this->invoker = $this->get(InvokerInterface::class);
                }
                $instance = $this->invoker->invoke([$definition, '__invoke'], ['parameters' => []]);
                if (!is_object($instance)) {
                    MisuseException::raise('Factory __invoke() must return object, got {type}', ['type' => gettype($instance)]);
                }
                return $instance;
            }
            // Object without __invoke - return as-is (singleton instance)
            return $definition;
        }

        // Handle array definition
        if (is_array($definition)) {
            // Extract class name and parameters
            $className = $definition['class'] ?? null;
            if ($className === null) {
                UnsupportedDefinitionException::raise('Array definition must have "class" key, got keys: {keys}', ['keys' => implode(', ', array_keys($definition))]);
            }
            unset($definition['class']);
            $parameters = $definition;

            // Resolve alias chain and interface
            $className = $this->resolveClassName($className);

            // Handle __invoke for callable classes
            if (method_exists($className, '__invoke')) {
                if (!isset($this->invoker)) {
                    $this->invoker = $this->get(InvokerInterface::class);
                }
                // Cache callable object by class name (not id) to allow sharing across aliases
                $object = $this->instances[$className] ?? null;
                if ($object === null) {
                    $object = $this->createInstance($className, [], $className);
                }
                $instance = $this->invoker->invoke([$object, '__invoke'], ['parameters' => $parameters]);
                if (!is_object($instance)) {
                    MisuseException::raise('{class}::__invoke() must return object (factory pattern), got {type}', ['class' => $className, 'type' => gettype($instance)]);
                }
                return $instance;
            }

            // Regular class - create instance with parameters
            return $this->createInstance($className, $parameters, $id);
        }

        // Handle string class name definition
        if (is_string($definition)) {
            // Resolve alias chain and interface
            $className = $this->resolveClassName($definition);

            // Handle __invoke for callable classes
            if (method_exists($className, '__invoke')) {
                if (!isset($this->invoker)) {
                    $this->invoker = $this->get(InvokerInterface::class);
                }
                // Cache callable object by class name (not id) to allow sharing across aliases
                $object = $this->instances[$className] ?? null;
                if ($object === null) {
                    $object = $this->createInstance($className, [], $className);
                }
                $instance = $this->invoker->invoke([$object, '__invoke'], ['parameters' => []]);
                if (!is_object($instance)) {
                    MisuseException::raise('{class}::__invoke() must return object (factory pattern), got {type}', ['class' => $className, 'type' => gettype($instance)]);
                }
                return $instance;
            }

            // Regular class - create instance
            return $this->createInstance($className, [], $id);
        }

        UnsupportedDefinitionException::raise('Unsupported service definition type: {type}, expected string (class name), array (config), or object (instance/factory)', ['type' => get_debug_type($definition)]);
    }

    /**
     * Resolves class name from alias chains and same-namespace interface mappings.
     *
     * Alias cycles or chains deeper than the internal limit raise <code>CircularDependencyException</code>.
     *
     * @throws CircularDependencyException
     * @throws NotFoundException
     */
    protected function resolveClassName(string $name): string
    {
        // Resolve alias chain (see method PHPDoc for depth limit rationale)
        $visited = [];
        $depth = 0;
        $maxDepth = 3;

        $definition = $this->definitions[$name] ?? null;
        while (is_string($definition) && !str_contains($definition, '#')) {
            if ($definition === $name) {
                break;
            }

            if (isset($visited[$name])) {
                CircularDependencyException::raise('Circular alias dependency: {path}',
                    ['path' => Strings::chain(array_keys($visited), ' -> ', $name)]);
            }

            if (++$depth > $maxDepth) {
                CircularDependencyException::raise('Alias chain exceeds max depth ({maxDepth}): {path}. Simplify your service definitions.', ['maxDepth' => $maxDepth, 'path' => Strings::chain(array_keys($visited), ' -> ', $name)]);
            }

            $visited[$name] = true;
            $name = $definition;
            $definition = $this->definitions[$name] ?? null;
        }

        // Validate class name format
        if (!str_starts_with($name, 'class@anonymous')) {
            if (preg_match('#^[\w\\\\]+$#', $name) !== 1) {
                NotFoundException::raise('Invalid class name format "{name}": must contain only letters, numbers, and backslashes', ['name' => $name]);
            }
        }

        // Interface auto-resolution (see class PHPDoc for details)
        $exists = false;
        if (str_ends_with($name, self::INTERFACE_SUFFIX) && interface_exists($name)) {
            $prefix = substr($name, 0, -self::INTERFACE_SUFFIX_LENGTH);
            if (class_exists($prefix)) {
                $exists = true;
                $name = $prefix;
            }
        } elseif (class_exists($name)) {
            $exists = true;
        }

        if (!$exists) {
            NotFoundException::raise('Class or interface "{name}" does not exist', ['name' => $name]);
        }

        return $name;
    }

    /**
     * Creates a new instance without singleton caching.
     *
     * @param string $name Class name, interface name, or alias.
     * @param array<string, mixed> $parameters Constructor arguments and property overrides.
     * @return mixed Fresh instance or factory result.
     * @throws NotFoundException
     * @throws CircularDependencyException
     */
    public function make(string $name, array $parameters = []): mixed
    {
        // Check for registered definition
        $registeredDefinition = $this->definitions[$name] ?? null;

        // Handle object definitions (factories with __invoke)
        if (is_object($registeredDefinition)) {
            return $this->createByDefinition($registeredDefinition, null);
        }

        // Determine class name from definition or use original name
        $className = $name;
        if ($registeredDefinition !== null) {
            if (is_string($registeredDefinition)) {
                $className = $registeredDefinition;
            } elseif (is_array($registeredDefinition) && isset($registeredDefinition['class'])) {
                $className = $registeredDefinition['class'];
            }
        }

        // Build definition with parameters
        $definition = $parameters;
        $definition['class'] = $className;

        // Create instance
        return $this->createByDefinition($definition, null);
    }

    /**
     * Resolves a service ID without an explicit definition.
     *
     * Enforces interface-first autowiring for class-like IDs.
     * Named IDs use <code>Type#name</code>; plain IDs still follow same-namespace interface auto-mapping.
     * Use this for fallback resolution when no explicit binding exists.
     *
     * @throws NotFoundException
     * @throws InterfaceAutowiringException
     */
    protected function getById(string $id): mixed
    {
        if (str_contains($id, '#')) {
            // Named service (Type#name) - check if definition exists
            if (isset($this->definitions[$id])) {
                return $this->getByDefinition($id, $this->definitions[$id]);
            }
            NotFoundException::raise('Named service "{id}" not found. Register it with $container->set() or configure via FactoryInterface.', ['id' => $id]);
        }

        // Enforce interface-first before instantiation (simple rule)
        if (!str_ends_with($id, self::INTERFACE_SUFFIX)) {
            $interfaceName = $id . self::INTERFACE_SUFFIX;
            if (interface_exists($interfaceName)) {
                InterfaceAutowiringException::raise('Inject interface {interface} instead of concrete class {class} (Dependency Inversion Principle)', ['interface' => $interfaceName, 'class' => $id]);
            }
        }

        $instance = null;
        try {
            $instance = $this->createByDefinition($id, $id);

            $this->instances[$id] = $instance;
            $this->dispatchEvent(new SingletonCreated($id, $instance, $this->definitions));
        } finally {
            if ($instance === null) {
                unset($this->instances[$id]);
            }
        }

        return $instance;
    }

    /** Resolves a service from an explicit definition and caches it as singleton. */
    protected function getByDefinition(string $id, mixed $definition): mixed
    {
        // Special handling: reference (contains #)
        if (is_string($definition) && str_contains($definition, '#')) {
            if (str_contains($id, '#')) {
                [$type] = explode('#', $id);
            } else {
                $type = $id;
            }
            return $this->instances[$id] = $this->get(str_starts_with($definition, '#') ? "$type$definition" : $definition);
        }

        // Special handling: interface
        if (is_string($definition) && interface_exists($definition)) {
            // Prevent infinite recursion: if definition is the same as id, calling get($definition)
            if ($definition === $id) {
                MisuseException::raise('Interface "{id}" is registered to itself: provide a concrete implementation class', ['id' => $id]);
            }
            return $this->instances[$id] = $this->get($definition);
        }

        // Special handling: object without __invoke (direct singleton instance)
        if (is_object($definition) && !method_exists($definition, '__invoke')) {
            return $this->instances[$id] = $definition;
        }

        // Special handling: array definition without 'class' key
        if (is_array($definition) && !isset($definition['class'])) {
            // Use id (or type part of id) as class name
            $class = ($position = strpos($id, '#')) === false ? $id : substr($id, 0, $position);
            $definition['class'] = $class;
        }

        // All other cases (object with __invoke, array with class, string class name) use createByDefinition
        $instance = $this->createByDefinition($definition, $id);
        $this->dispatchEvent(new SingletonCreated($id, $instance, $this->definitions));
        return $this->instances[$id] = $instance;
    }

    /** {@inheritDoc} */
    public function get(string $id): mixed
    {
        $instance = $this->instances[$id] ?? null;
        if ($instance !== null) {
            return $instance;
        }

        $definition = $this->definitions[$id] ?? null;
        return $definition === null ? $this->getById($id) : $this->getByDefinition($id, $definition);
    }

    /**
     * @return array<string, mixed> Registered definitions keyed by service ID.
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return mixed Raw definition registered for the ID, or null when missing.
     */
    public function getDefinition(string $id): mixed
    {
        return $this->definitions[$id] ?? null;
    }

    /**
     * @return array<string, mixed> Resolved singleton instances keyed by service ID.
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

    /** {@inheritDoc} */
    public function has(string $id): bool
    {
        if (isset($this->instances[$id])) {
            return true;
        } elseif (isset($this->definitions[$id])) {
            return true;
        } elseif (str_contains($id, '#')) {
            return false;
        } elseif (preg_match('#^[\w\\\\]+$#', $id) === 1) {
            // Interface auto-resolution (see class PHPDoc for details)
            if (str_ends_with($id, self::INTERFACE_SUFFIX)) {
                if (!interface_exists($id)) {
                    return false;
                }

                return class_exists(substr($id, 0, -self::INTERFACE_SUFFIX_LENGTH));
            } else {
                return class_exists($id);
            }
        }

        return false;
    }

    /** {@inheritDoc} */
    /** @internal */
    public function isResolved(string $id): bool
    {
        return isset($this->instances[$id]);
    }
}
