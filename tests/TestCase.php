<?php

declare(strict_types=1);

namespace Switon\Di\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switon\Core\InjectorInterface;
use Switon\Di\Container;
use Switon\Di\ServiceProvider;

/**
 * Base test case for DI tests.
 *
 * Provides common functionality for all DI tests, including Container initialization.
 */
abstract class TestCase extends BaseTestCase
{
    protected Container $container;
    protected InjectorInterface $injector;

    /**
     * Helper to create a DI container with DI ServiceProvider registered.
     *
     * Used by tests that need multiple independent containers.
     *
     * @param array<string, mixed> $definitions
     */
    protected function createContainer(array $definitions = []): Container
    {
        $container = new Container($definitions);

        $provider = new ServiceProvider();
        $provider->register($container);
        $provider->boot();

        return $container;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Default container for most tests
        $this->container = $this->createContainer();
        $this->injector = $this->container->get(InjectorInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->container);
    }

    /**
     * Creates a test event dispatcher stub that collects dispatched events.
     *
     * Returns an EventDispatcherInterface implementation that stores all
     * dispatched events in a public array for testing purposes.
     *
     * @return \Psr\EventDispatcher\EventDispatcherInterface Event dispatcher stub with dispatchedEvents array
     */
    protected function createEventDispatcherStub(): \Psr\EventDispatcher\EventDispatcherInterface
    {
        return new class implements \Psr\EventDispatcher\EventDispatcherInterface {
            public array $dispatchedEvents = [];

            public function dispatch(object $event): object
            {
                $this->dispatchedEvents[] = $event;
                return $event;
            }
        };
    }
}

