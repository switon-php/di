<?php

declare(strict_types=1);

namespace Switon\Di;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassName;
use Switon\Core\ContainerInterface;
use Switon\Core\InjectorInterface;
use Switon\Core\Lazy;
use Switon\Di\Event\FactoryObjectInjected;
use Switon\Di\Exception\InterfaceAutowiringException;
use Switon\Di\Exception\MissingConfigurationException;
use Switon\Di\Exception\MissingTypeDeclarationException;
use Switon\Di\Exception\NotFoundException;
use Switon\Di\Exception\ServiceInjectionException;
use Traversable;
use function array_key_exists;
use function count;
use function interface_exists;
use function is_array;
use function is_string;
use function iterator_to_array;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * Injects <code>#[Autowired]</code> properties from container services and config values.
 *
 * Guidance: Property injection is interface-first; use explicit IDs only for named services, aliases, or lazy resolution.
 * Guidance: Use this at object construction boundaries, not for general-purpose service location.
 *
 * Road-signs:
 * - inject <code>#[Autowired]</code>
 * - resolve service <code>resolveDependency()</code>
 * - arrays via <code>injectAutowiredArray()</code> or <code>injectInstances()</code>
 * - named service resolution emits <code>FactoryObjectInjected</code>
 * - union autowiring only works when the target stays unambiguous
 * - scalar and array values come from parameters or defaults, not service lookup
 *
 * @see \Switon\Core\InjectorInterface
 * @see \Switon\Core\Attribute\Autowired
 * @see \Switon\Di\Container
 * @see \Switon\Di\Injector::resolveDependency()
 * @see \Switon\Di\Event\FactoryObjectInjected
 */
class Injector implements InjectorInterface
{
    protected const EXCEPTION_AMBIGUOUS_MULTIPLE_INTERFACES = '{class}::${property} union type contains multiple interfaces ({interfaces}), use a single interface or bind the property explicitly in config';
    protected const EXCEPTION_AMBIGUOUS_MULTIPLE_TYPES = '{class}::${property} union type has {count} ambiguous types ({types}), use single type or Lazy|Type pattern';

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /** {@inheritDoc} */
    public function inject(object $object, array $parameters = [], ?ReflectionClass $rClass = null): void
    {
        $rClass ??= new ReflectionClass($object);

        // Get all properties including inherited ones (public, protected, and private)
        // Private properties are supported to align with Autowired attribute documentation.
        $properties = $rClass->getProperties(
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE
        );

        foreach ($properties as $property) {
            // Only process properties with #[Autowired] attribute
            $autowiredAttrs = $property->getAttributes(Autowired::class);
            if (empty($autowiredAttrs)) {
                continue;
            }

            $autowiredAttribute = $autowiredAttrs[0]->newInstance();
            $isInstances = $autowiredAttribute->isInstances();

            if ($property->hasType()) {
                $rType = $property->getType();
                if ($rType instanceof ReflectionIntersectionType) {
                    ServiceInjectionException::raise('{class}::${property} uses intersection type (A&B) which is not supported for autowiring, use single type instead', ['class' => $object::class, 'property' => $property->getName()]);
                }
                $type = $this->getFirstType($rType);

                if ($type->isBuiltin()) {
                    $typeName = $type->getName();
                    if ($typeName === 'array') {
                        if ($isInstances) {
                            $this->injectInstances($property, $object, $parameters);
                        } else {
                            $this->injectAutowiredArray($property, $object, $parameters);
                        }
                    } else {
                        $this->injectAutowiredScalar($property, $object, $parameters);
                    }
                } else {
                    $this->injectAutowiredObject($property, $object, $parameters);
                }
            } else {
                MissingTypeDeclarationException::raise('{class}::${property} marked with #[Autowired] must have a type declaration', ['class' => $object::class, 'property' => $property->getName()]);
            }
        }
    }


    /** Returns first named type from a reflection type (named/union). */
    protected function getFirstType(ReflectionType $rType): ReflectionNamedType
    {
        if ($rType instanceof ReflectionNamedType) {
            return $rType;
        }

        // ReflectionUnionType has getTypes()
        /** @var ReflectionUnionType $rType */
        return $rType->getTypes()[0];
    }

    /** Returns string form of reflection type. */
    protected function getTypeName(ReflectionType $rType): string
    {
        return $rType instanceof ReflectionNamedType ? $rType->getName() : (string)$rType;
    }

    /** Dispatches DI event when dispatcher is available. */
    protected function dispatchEvent(object $event): void
    {
        if ($this->container->has(EventDispatcherInterface::class)) {
            $dispatcher = $this->container->get(EventDispatcherInterface::class);
            if ($dispatcher instanceof EventDispatcherInterface) {
                $dispatcher->dispatch($event);
            }
        }
    }


    /**
     * {@inheritDoc}
     *
     * Guidance: Relative references like <code>#redisDb</code> are config syntax; property names stay the slot selector.
     */
    public function resolveDependency(string $type, string $name, ?string $value): object
    {
        if ($value !== null) {
            if (str_contains($value, '#')) {
                $this->dispatchEvent(new FactoryObjectInjected($type, $name, str_starts_with($value, '#') ? "$type$value" : $value));
            }
            $value = $this->container->get(str_starts_with($value, '#') ? "$type$value" : $value);
        } else {
            $alias = "$type#$name";
            if ($this->container->has($alias)) {
                $this->dispatchEvent(new FactoryObjectInjected($type, $name, $alias));
                $value = $this->container->get($alias);
            } else {
                $value = $this->container->get($type);
            }
        }

        return $value;
    }

    /**
     * Injects non-builtin <code>#[Autowired]</code> object properties.
     *
     * Supports explicit IDs, <code>Type#property</code> aliases, union selection, and <code>Lazy|Type</code>.
     */
    protected function injectAutowiredObject(ReflectionProperty $property, object $object, array $parameters): void
    {
        $name = $property->getName();

        $rType = $property->getType();
        if ($rType === null) {
            MissingTypeDeclarationException::raise('{class}::${property} marked with #[Autowired] must have a type declaration', ['class' => $object::class, 'property' => $name]);
        }

        $this->guardInterfaceFirstAutowired($rType, $object, $name);

        $hasExplicitValue = array_key_exists($name, $parameters);
        $value = $hasExplicitValue ? $parameters[$name] : null;

        // Also check by type name (allows passing [ContainerInterface::class => $instance] or [ContainerInterface::class => '#cache'])
        // Supports both object instances and string references (like '#cache')
        // Only check type name if property name didn't provide a value
        if (!$hasExplicitValue && $value === null) {
            if ($rType instanceof ReflectionUnionType) {
                // For union types, try to find a matching type in parameters
                foreach ($rType->getTypes() as $type) {
                    $typeName = $type->getName();
                    if ($typeName !== Lazy::class && !$type->isBuiltin()) {
                        if (array_key_exists($typeName, $parameters)) {
                            $value = $parameters[$typeName];
                            $hasExplicitValue = true;
                            break;
                        }
                    }
                }
            } else {
                $type = $this->getTypeName($rType);
                if (array_key_exists($type, $parameters)) {
                    $value = $parameters[$type];
                    $hasExplicitValue = true;
                }
            }
        }

        // Inline array: make() it. Has 'class' → use it; no 'class' → use property type (container resolves interface).
        if (is_array($value)) {
            $className = $value['class'] ?? $this->getTypeName($rType);
            unset($value['class']);
            $value = $this->container->make($className, $value);
        }

        if ($value === null || is_string($value)) {
            if ($rType instanceof ReflectionUnionType) {
                $types = $rType->getTypes();

                $hasLazy = false;
                $interfaceTypes = [];
                $nonFlagTypes = [];

                foreach ($types as $type) {
                    $typeName = $type->getName();

                    if ($typeName === Lazy::class) {
                        $hasLazy = true;
                        continue;
                    }

                    if ($type->isBuiltin()) {
                        continue;
                    }

                    $nonFlagTypes[] = $typeName;

                    if (str_ends_with($typeName, 'Interface') && interface_exists($typeName)) {
                        $interfaceTypes[] = $typeName;
                    }
                }
                if (count($interfaceTypes) > 1) {
                    ServiceInjectionException::raise(self::EXCEPTION_AMBIGUOUS_MULTIPLE_INTERFACES,
                        ['class' => $object::class, 'property' => $name, 'interfaces' => implode(', ', $interfaceTypes)]
                    );
                } elseif (count($interfaceTypes) === 1 || count($nonFlagTypes) === 1) {
                    $type = count($interfaceTypes) === 1 ? $interfaceTypes[0] : $nonFlagTypes[0];
                    if ($hasLazy) {
                        // If Lazy is declared, always use lazy proxy
                        // This respects user's intent to defer resolution
                        $value = new LazyPropertyProxy($this->container, $property, $object, $type, $value);
                    } else {
                        $value = $this->getAutowiredObjectOrThrow($type, $object::class, $name, $value);
                    }
                } else {
                    $allTypes = !empty($nonFlagTypes) ? implode(', ', $nonFlagTypes) : 'none';
                    ServiceInjectionException::raise(self::EXCEPTION_AMBIGUOUS_MULTIPLE_TYPES,
                        ['class' => $object::class, 'property' => $name, 'count' => count($nonFlagTypes), 'types' => $allTypes]
                    );
                }
            } else {
                $type = $this->getTypeName($rType);
                $value = $this->getAutowiredObjectOrThrow($type, $object::class, $name, $value);
            }
        }

        $property->setValue($object, $value);
    }

    protected function guardInterfaceFirstAutowired(ReflectionType $rType, object $object, string $propertyName): void
    {
        if ($rType instanceof ReflectionUnionType) {
            return;
        }

        $typeName = $this->getTypeName($rType);
        if ($typeName === Lazy::class || ClassName::isInterface($typeName)) {
            return;
        }

        $interfaceName = $typeName . 'Interface';
        if (!interface_exists($interfaceName)) {
            return;
        }

        InterfaceAutowiringException::raise(
            'Inject interface {interface} instead of concrete class {class} at {owner}::${property} (Dependency Inversion Principle)',
            ['interface' => $interfaceName, 'class' => $typeName, 'owner' => $object::class, 'property' => $propertyName]
        );
    }

    /**
     * Resolves one object dependency or raises a user-facing injection error.
     */
    protected function getAutowiredObjectOrThrow(string $type, string $class, string $property, ?string $value): object
    {
        try {
            return $this->resolveDependency($type, $property, $value);
        } catch (NotFoundException) {
            // Build simple error message showing what was tried
            $specificId = "$type#$property";
            $tried = $value === null
                ? "$specificId, $type"
                : (str_starts_with($value, '#') ? "$type$value" : $value);

            $hint = str_ends_with($type, 'Interface')
                ? 'Check spelling or register implementation in config'
                : 'Check namespace, spelling, or register in config';

            ServiceInjectionException::raise(
                '{class}::${property} cannot resolve service of type {type}: not registered in container (tried: {tried}). Hint: {hint}',
                [
                    'class' => $class,
                    'property' => $property,
                    'type' => $type,
                    'tried' => $tried,
                    'hint' => $hint,
                ]
            );
        }
    }

    /** Applies null/default fallback when an autowired value is missing. */
    protected function injectAutowiredNoValue(ReflectionProperty $property, object $object): void
    {
        $rType = $property->getType();

        // If property type allows null, set it to null (optional dependency)
        // This handles nullable types gracefully without requiring explicit configuration
        if ($rType !== null && $rType->allowsNull()) {
            $property->setValue($object, null);
        } else {
            // Non-nullable type without value - this is an error
            // User must provide value via parameters or default value
            MissingConfigurationException::raise('{class}::${property} is not nullable and has no default value, provide value in config', ['class' => $property->class, 'property' => $property->getName()]);
        }
    }

    /** Injects scalar autowired values from parameters or defaults. */
    protected function injectAutowiredScalar(ReflectionProperty $property, object $object, array $parameters): void
    {
        $name = $property->getName();

        // 1. Parameters take priority (allows runtime configuration override)
        // This enables injecting scalar values (strings, ints, etc.) from config at object creation
        if (array_key_exists($name, $parameters)) {
            $value = $parameters[$name];
            $property->setValue($object, $value);
            return;
        }

        // 2. If has default value, use it (no injection needed)
        // Default values are already set by PHP, so we just leave them as-is
        if ($property->hasDefaultValue()) {
            return;
        }

        // 3. No value found, handle based on type nullability
        // Nullable types get null, non-nullable types throw exception
        if ($property->hasType()) {
            $this->injectAutowiredNoValue($property, $object);
        }
    }

    /** Merges array config with the property default; null removes keys. */
    protected function mergeArrayWithDefault(mixed $configValue, ReflectionProperty $property): array
    {
        // 1. Handle Traversable (full override, no merging)
        // When user provides Traversable, they want full control - no merging with default
        if ($configValue instanceof Traversable) {
            return $this->handleTraversableValue($configValue);
        }

        // 2. Handle array with default value
        if (is_array($configValue) && $property->hasDefaultValue()) {
            $defaultValue = $property->getDefaultValue();

            // Only merge if default value is not empty (empty defaults are overridden directly)
            if (!empty($defaultValue)) {
                return $this->mergeWithDefault($configValue, $defaultValue);
            }
        }

        // 3. Handle array without default or non-array
        // No default value means config is used directly (after filtering nulls)
        return $this->filterNullValues(is_array($configValue) ? $configValue : []);
    }

    /** Converts Traversable config to plain array. */
    protected function handleTraversableValue(Traversable $value): array
    {
        return iterator_to_array($value);
    }

    /** Removes default keys explicitly nulled by config. */
    protected function removeNullKeysFromDefault(array $default, array $config): array
    {
        foreach ($default as $key => $defaultVal) {
            if (array_key_exists($key, $config) && $config[$key] === null) {
                unset($default[$key], $config[$key]);
            }
        }

        return $default;
    }

    /** Merges config over default and filters null markers from the result. */
    protected function mergeWithDefault(array $config, array $default): array
    {
        // Remove keys from default when config has null (null removes keys)
        $default = $this->removeNullKeysFromDefault($default, $config);

        // If default becomes empty after removing keys, return filtered config
        // This preserves the structure of config when all defaults are removed
        if (empty($default)) {
            return $this->filterNullValues($config);
        }

        // Merge: default first, then config (config overrides default)
        $result = [...$default, ...$config];

        // Remove null values (null only used to remove keys, not to keep in final result)
        return $this->filterNullValues($result);
    }

    /** Drops null values from a merged array result. */
    protected function filterNullValues(array $array): array
    {
        return array_filter($array, static fn($value) => $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    /** Injects plain array autowired values with default merge semantics. */
    protected function injectAutowiredArray(ReflectionProperty $property, object $object, array $parameters): void
    {
        $name = $property->getName();

        // 1. Parameters take priority (allows runtime configuration override)
        // This enables dependency injection with custom values at object creation time
        if (array_key_exists($name, $parameters)) {
            $value = $this->mergeArrayWithDefault($parameters[$name], $property);

            $property->setValue($object, $value);
            return;
        }

        // 2. If has default value, use it (no injection needed)
        // Default values are already set by PHP, so we just leave them as-is
        if ($property->hasDefaultValue()) {
            return;
        }

        // 3. No value found, handle based on type nullability
        // Nullable types get null, non-nullable types throw exception
        if ($property->hasType()) {
            $this->injectAutowiredNoValue($property, $object);
        }
    }

    /**
     * Injects <code>#[Autowired(instances: true)]</code> arrays.
     *
     * Each entry must be a service ID string or an inline definition with <code>class</code>.
     */
    protected function injectInstances(ReflectionProperty $property, object $object, array $parameters): void
    {
        $name = $property->getName();

        // Get array from parameters or default value
        // Instances mode requires array of service IDs that will be converted to objects
        if (array_key_exists($name, $parameters)) {
            $paramValue = $parameters[$name];

            // Validate that parameter is array or Traversable
            // Traversable is allowed because it can be converted to array
            if (!is_array($paramValue) && !($paramValue instanceof Traversable)) {
                MissingConfigurationException::raise(
                    '{class}::${property} with #[Autowired(instances: true)] requires array config, got {type}',
                    ['class' => $property->class, 'property' => $name, 'type' => get_debug_type($paramValue)]
                );
            }

            // Merge with default if default exists (allows partial overrides)
            $array = $this->mergeArrayWithDefault($paramValue, $property);
        } elseif ($property->hasDefaultValue()) {
            // Use default value if no parameter provided
            $array = $property->getDefaultValue();
        } else {
            // No value and no default - this is required for instances mode
            MissingConfigurationException::raise(
                '{class}::${property} with #[Autowired(instances: true)] requires config or default value',
                ['class' => $property->class, 'property' => $name]
            );
        }

        // Double-check after merging (merge might return non-array in edge cases)
        if (!is_array($array)) {
            MissingConfigurationException::raise(
                '{class}::${property} with #[Autowired(instances: true)] requires array, got {type} after merge',
                ['class' => $property->class, 'property' => $name, 'type' => get_debug_type($array)]
            );
        }

        // Convert service IDs or inline definitions to actual instances
        // String: service ID resolved via get() (singleton)
        // Array with 'class' key: inline definition resolved via make() (new instance with parameters)
        // Array without 'class' key: invalid, throw
        $instances = [];
        foreach ($array as $key => $id) {
            try {
                if (is_array($id)) {
                    if (!isset($id['class'])) {
                        MissingConfigurationException::raise(
                            '{class}::${property}["{key}"]: inline definition requires "class" key',
                            ['class' => $property->class, 'property' => $name, 'key' => $key]
                        );
                    }
                    $instances[$key] = $this->container->make($id['class'], $id);
                } elseif (is_string($id)) {
                    $instances[$key] = $this->container->get($id);
                } else {
                    MissingConfigurationException::raise(
                        '{class}::${property}["{key}"]: expected string (service ID) or array with "class" key, got {type}',
                        ['class' => $property->class, 'property' => $name, 'key' => $key, 'type' => get_debug_type($id)]
                    );
                }
            } catch (NotFoundException $e) {
                ServiceInjectionException::raise('{class}::${property}["{key}"]: service "{service}" not found in container', ['class' => $property->class, 'property' => $name, 'service' => is_string($id) ? $id : ($id['class'] ?? 'unknown'), 'key' => $key]);
            }
        }

        $property->setValue($object, $instances);
    }
}
