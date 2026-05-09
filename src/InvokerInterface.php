<?php

declare(strict_types=1);

namespace Switon\Di;

/**
 * Invokes callables with resolved parameters.
 *
 * Use when framework code needs a callable execution contract without interceptor semantics.
 *
 * @see \Switon\Di\Container
 * @see \Switon\Di\InjectorInterface::resolveDependency()
 */
interface InvokerInterface
{
    /**
     * Invoke a callable, including invokable objects, with resolved parameters.
     *
     * @param callable $callable
     * @param array<int|string, mixed> $parameters Positional, named, type-keyed, or variadic overrides
     * @return mixed Callable return value
     */
    public function invoke(callable $callable, array $parameters = []): mixed;
}
