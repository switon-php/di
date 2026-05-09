<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Tests\Fixtures\{TestService,
    TestServiceInterface,
    TestServiceWithFailingConstructor,
    TestServiceWithParams,
    TestServiceWithRequiredProperty
};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container make() method.
 *
 * Tests make() method functionality: creating new instances, parameter injection,
 * error handling, and special cases like __invoke() handling.
 */
class ContainerMakeTest extends TestCase
{
    public function testMakeCreatesNewInstance(): void
    {
        // Act
        $instance1 = $this->container->make(TestService::class);
        $instance2 = $this->container->make(TestService::class);

        // Assert
        $this->assertInstanceOf(TestService::class, $instance1);
        $this->assertInstanceOf(TestService::class, $instance2);
        $this->assertNotSame($instance1, $instance2, 'make() should create new instances each time');
    }

    public function testMakeWithParameters(): void
    {
        // Arrange
        $parameters = [
            'name' => 'CustomName',
            'value' => 100,
        ];

        // Act
        $instance = $this->container->make(TestServiceWithParams::class, $parameters);

        // Assert
        $this->assertInstanceOf(TestServiceWithParams::class, $instance);
        $this->assertSame('CustomName', $instance->name, 'make() should inject name parameter');
        $this->assertSame(100, $instance->value, 'make() should inject value parameter');
    }

    /**
     * Test that make() correctly handles classes with __invoke method.
     *
     * Verifies that:
     * 1. make() can create instances from callable classes
     * 2. The callable object is cached for reuse
     * 3. Each call to make() invokes __invoke() and returns a new result
     */
    public function testMakeWithInvokeMethodCreatesAndCachesCallable(): void
    {
        // Arrange - Create a callable class
        $callableClass = new class {
            public function __invoke(array $parameters = []): object
            {
                return new TestService();
            }
        };

        $className = get_class($callableClass);

        // Act - First call creates and caches the callable object
        $result1 = $this->container->make($className, ['parameters' => []]);

        // Act - Second call should use cached callable object
        $result2 = $this->container->make($className, ['parameters' => []]);

        // Assert - Both calls return TestService instances
        $this->assertInstanceOf(TestService::class, $result1,
            'First call should return result from __invoke()');
        $this->assertInstanceOf(TestService::class, $result2,
            'Second call should return result from __invoke()');

        // Assert - Callable object is cached (verify by checking instances cache)
        $this->assertArrayHasKey($className, $this->container->getInstances(),
            'Callable object should be cached');
        $this->assertInstanceOf($className, $this->container->getInstances()[$className],
            'Cached instance should be the callable object');
    }

    /**
     * Test that make() throws NotFoundException for invalid class name format.
     *
     * Verifies that make() validates class name format and throws exception
     * when class name contains invalid characters or format.
     */
    public function testMakeThrowsNotFoundExceptionForInvalidClassNameFormat(): void
    {
        // Act & Assert
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        $this->container->make('NonExistentClass123');
    }

    /**
     * Test that make() throws NotFoundException for non-existent class.
     *
     * Verifies that make() throws exception when class does not exist.
     */
    public function testMakeThrowsNotFoundExceptionForNonExistentClass(): void
    {
        // Act & Assert
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        $this->container->make('Tests\NonExistentClass');
    }

    /**
     * Test that make() throws NotFoundException for class name with special characters.
     *
     * Verifies that make() rejects class names containing special characters
     * that are not valid in PHP class names.
     */
    public function testMakeThrowsNotFoundExceptionForInvalidClassNameWithSpecialChars(): void
    {
        // Act & Assert
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        $this->container->make('Invalid@Class#Name');
    }

    /**
     * Test that get() propagates exceptions thrown during constructor execution.
     *
     * Verifies that when a service constructor throws an exception,
     * the exception is properly propagated to the caller.
     */
    public function testMakeWithConstructorFailureThrowsException(): void
    {
        // Arrange
        $this->container->set(TestServiceWithFailingConstructor::class, TestServiceWithFailingConstructor::class);

        // Act & Assert
        $this->expectException(\RuntimeException::class);

        $this->container->get(TestServiceWithFailingConstructor::class);
    }

    /**
     * Test that make() creates new instances while get() uses cached instances.
     *
     * Verifies the difference between make() and get():
     * - make() always creates new instances
     * - get() returns cached singleton instances
     */
    public function testMakeWithoutConstructorCachesInstance(): void
    {
        // Arrange
        $service = new class {
            public $value = 'test';
        };

        $className = get_class($service);

        // Act
        $instance1 = $this->container->make($className);
        $instance2 = $this->container->make($className);

        // Assert - make() creates new instances
        $this->assertNotSame($instance1, $instance2, 'make() should create new instances each time');

        // Act - get() uses cache
        $cached1 = $this->container->get($className);
        $cached2 = $this->container->get($className);

        // Assert - get() should return same instance
        $this->assertSame($cached1, $cached2, 'get() should return cached singleton instance');
    }

    /**
     * Test that get() throws MissingConfigurationException when required property is not configured.
     *
     * Verifies that when a service has a required property (no default value)
     * and no configuration is provided, an exception is thrown.
     */
    public function testMakeWithoutConstructorWithPropertyInjectionFailureThrowsException(): void
    {
        // Arrange
        $this->container->set(TestServiceWithRequiredProperty::class, TestServiceWithRequiredProperty::class);

        // Act & Assert
        $this->expectException(\Switon\Di\Exception\MissingConfigurationException::class);

        $this->container->get(TestServiceWithRequiredProperty::class);
    }

    /**
     * Test that make() auto-resolves interfaces to implementation classes.
     *
     * Verifies that when an interface name is provided (following Switon naming convention),
     * make() automatically resolves it to the corresponding implementation class.
     */
    public function testMakeWithInterfaceSuffix(): void
    {
        // Act
        $service = $this->container->make(TestServiceInterface::class);

        // Assert
        $this->assertInstanceOf(TestService::class, $service,
            'make() should auto-resolve interface to implementation class');
    }
}

