<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container singleton behavior.
 *
 * Tests that get() returns cached instances, ensuring singleton behavior.
 */
class ContainerSingletonTest extends TestCase
{
    /**
     * Test that get() returns cached instances for singleton behavior.
     *
     * Verifies that multiple calls to get() with the same service ID
     * return the same cached instance, ensuring singleton behavior.
     */
    public function testGetWithCachedInstance(): void
    {
        // Arrange
        $service1 = new TestService();
        $this->container->set(TestService::class, $service1);

        // Act
        $instance1 = $this->container->get(TestService::class);
        $instance2 = $this->container->get(TestService::class);

        // Assert
        $this->assertSame($instance1, $instance2, 'Multiple calls to get() should return the same cached instance');
        $this->assertSame($service1, $instance1, 'get() should return the registered instance');
    }

    public function testSingletonBehavior(): void
    {
        // Arrange
        $this->container->set(TestService::class, TestService::class);

        // Act
        $service1 = $this->container->get(TestService::class);
        $service2 = $this->container->get(TestService::class);

        // Assert
        $this->assertSame($service1, $service2, 'get() should return the same singleton instance');
    }

    /**
     * Test that get() correctly handles registered callable services.
     *
     * Verifies that when a callable class is registered as a service,
     * get() invokes __invoke() and returns the result as a singleton.
     */
    public function testGetWithInvokeMethodReturnsSingletonFromCallable(): void
    {
        // Arrange - Register a callable class as a service
        $callable = new class {
            public function __invoke(array $parameters = []): object
            {
                return new TestService();
            }
        };

        $this->container->set('callable.service', $callable::class);

        // Act - Get the service (should invoke __invoke and cache result)
        $result1 = $this->container->get('callable.service');
        $result2 = $this->container->get('callable.service');

        // Assert - Result is a TestService instance
        $this->assertInstanceOf(TestService::class, $result1,
            'Service with __invoke() should return an object');

        // Assert - Result is cached as singleton
        $this->assertSame($result1, $result2,
            'get() should return the same cached instance for callable services');
    }
}

