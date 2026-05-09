<?php

declare(strict_types=1);

namespace Switon\Di;

use Switon\Core\ContainerInterface;
use Switon\Core\InjectorInterface;
use Switon\Core\ServiceProviderInterface;

/**
 * Wires the DI injector and invoker into the application container.
 *
 * Guidance: Use this at bootstrap only; ordinary services should not depend on the provider.
 *
 * Road-signs:
 * - register <code>InjectorInterface</code>
 * - register <code>InvokerInterface</code> when no explicit binding exists
 * - use the container's explicit binding rules first
 *
 * @see \Switon\Core\ServiceProviderInterface
 * @see \Switon\Di\Container
 * @see \Switon\Di\Injector
 * @see \Switon\Invoking\Invoker
 */
class ServiceProvider implements ServiceProviderInterface
{
    /** {@inheritDoc} */
    public function register(ContainerInterface $container): void
    {
        if (!$container->has(InjectorInterface::class)) {
            $container->set(InjectorInterface::class, new Injector($container));
        }

        $hasInvoker = $container instanceof Container
            ? $container->getDefinition(InvokerInterface::class) !== null
            || isset($container->getInstances()[InvokerInterface::class])
            : $container->has(InvokerInterface::class);

        if ($hasInvoker) {
            return;
        }

        $injector = $container->get(InjectorInterface::class);
        $invoker = new Invoker();
        $injector->inject($invoker);

        $container->set(InvokerInterface::class, $invoker);
    }

    /** {@inheritDoc} */
    public function boot(): void
    {
    }
}
