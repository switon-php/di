<?php

declare(strict_types=1);

namespace Switon\Di;

use Psr\Container\NotFoundExceptionInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ContainerInterface;
use Switon\Core\InjectorInterface;
use Switon\Di\Exception\MissingConfigurationException;
use Switon\Di\Exception\MissingTypeDeclarationException;
use Switon\Di\Exception\ServiceInjectionException;
use function array_key_exists;
use function array_values;
use function is_array;
use function is_object;
use function str_ends_with;

/**
 * Invokes callables with explicit parameters and DI resolution.
 *
 * Use when framework code needs a callable execution contract without invocation extras.
 *
 * @see \Switon\Di\InvokerInterface
 * @see \Switon\Core\InjectorInterface::resolveDependency()
 */
class Invoker implements InvokerInterface
{
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected InjectorInterface $injector;

    /** {@inheritDoc} */
    public function invoke(callable $callable, array $parameters = []): mixed
    {
        $rFunction = $this->reflectCallable($callable);

        $args = [];
        foreach ($rFunction->getParameters() as $position => $rParameter) {
            $name = $rParameter->getName();

            $rType = $rParameter->getType();
            $type = ($rType instanceof ReflectionNamedType && !$rType->isBuiltin()) ? $rType->getName() : null;

            if ($rParameter->isVariadic()) {
                if (array_key_exists($name, $parameters)) {
                    $values = is_array($parameters[$name]) ? array_values($parameters[$name]) : [$parameters[$name]];
                } elseif ($type !== null && array_key_exists($type, $parameters)) {
                    $values = is_array($parameters[$type]) ? array_values($parameters[$type]) : [$parameters[$type]];
                } else {
                    $values = [];
                    foreach ($parameters as $key => $value) {
                        if (is_int($key) && $key >= $position) {
                            $values[$key] = $value;
                        }
                    }

                    if ($values !== []) {
                        ksort($values);
                        $values = array_values($values);
                    }
                }

                foreach ($values as $value) {
                    $args[] = $this->resolveDependency($value, $type, $callable, $rFunction, $name);
                }
                continue;
            }

            if (array_key_exists($position, $parameters)) {
                $value = $parameters[$position];
            } elseif (array_key_exists($name, $parameters)) {
                $value = $parameters[$name];
            } elseif ($rParameter->isDefaultValueAvailable()) {
                $value = $rParameter->getDefaultValue();
            } elseif ($type !== null) {
                $value = $parameters[$type] ?? null;
            } else {
                $signature = is_array($callable)
                    ? $callable[0]::class . '::' . $callable[1]
                    : $rFunction->getName();

                if ($rType === null) {
                    MissingTypeDeclarationException::raise(
                        '{method}() parameter ${parameter} requires a type declaration',
                        ['method' => $signature, 'parameter' => $name]
                    );
                }

                MissingConfigurationException::raise(
                    '{method}() parameter ${parameter} requires a value or default',
                    ['method' => $signature, 'parameter' => $name]
                );
            }

            $args[] = $this->resolveDependency($value, $type, $callable, $rFunction, $name);
        }
        return $callable(...$args);
    }

    protected function reflectCallable(callable $callable): ReflectionFunction|ReflectionMethod
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_object($callable) && !$callable instanceof \Closure) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction($callable);
    }

    protected function resolveDependency(
        mixed $value,
        ?string $type,
        callable $callable,
        ReflectionFunction|ReflectionMethod $rFunction,
        string $name,
    ): mixed {
        if ($type === null || is_object($value)) {
            return $value;
        }

        try {
            return $this->injector->resolveDependency($type, $name, $value);
        } catch (NotFoundExceptionInterface) {
            $hint = str_ends_with($type, 'Interface')
                ? 'Check spelling or register implementation in config'
                : 'Check namespace, spelling, or register in config';

            if (is_array($callable)) {
                $target = is_object($callable[0]) ? $callable[0]::class : $callable[0];
                $method = $target . '::' . $callable[1];
            } elseif ($rFunction instanceof ReflectionMethod) {
                $method = $rFunction->getDeclaringClass()->getName() . '::' . $rFunction->getName();
            } else {
                $method = $rFunction->getName();
            }

            ServiceInjectionException::raise(
                '{method}() cannot resolve parameter ${parameter} of type {type}. Hint: {hint}',
                [
                    'method' => $method,
                    'parameter' => $name,
                    'type' => $type,
                    'hint' => $hint,
                ]
            );
        }
    }
}
