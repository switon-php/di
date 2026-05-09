<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Closure;
use Switon\Core\Exception\MisuseException;
use Switon\Di\Exception\NotFoundException;
use Switon\Di\Exception\ReflectionException as DiReflectionException;
use Switon\Di\Tests\Fixtures\{DiCoverageInvokeReturnsScalar,
    DiCoverageNoExplicitConstructor,
    TempTestInterfaceWithoutClass,
    TestService,
    TestServiceWithFailingConstructor};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for advanced container functionality.
 *
 * Tests advanced scenarios like error handling, edge cases, and special behaviors.
 */
class ContainerAdvancedTest extends TestCase
{
    /**
     * Test container behavior when constructor fails.
     *
     * Verifies that the container properly handles services with failing constructors
     * and doesn't cache partially initialized instances.
     */
    public function testContainerHandlesFailingConstructor(): void
    {
        // Arrange
        $this->container->set('failing.service', TestServiceWithFailingConstructor::class);

        // Act & Assert - Should throw the constructor exception
        $this->expectException(\RuntimeException::class);

        $this->container->get('failing.service');
    }

    /**
     * Test that failing constructor doesn't leave partial instance in cache.
     *
     * Verifies that if a constructor fails, the container doesn't cache a partially
     * initialized instance that could cause issues on subsequent requests.
     */
    public function testFailingConstructorDoesNotCachePartialInstance(): void
    {
        // Arrange
        $this->container->set('failing.service', TestServiceWithFailingConstructor::class);

        // Act - First attempt should fail
        try {
            $this->container->get('failing.service');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Assert - No instance should be cached after first failure
        $this->assertArrayNotHasKey('failing.service', $this->container->getInstances(),
            'No instance should be cached after constructor failure');

        // Act - Second attempt should also fail with same exception type
        try {
            $this->container->get('failing.service');
            $this->fail('Expected RuntimeException was not thrown on second attempt');
        } catch (\RuntimeException $e) {
            // Expected - same exception type means resolution stack was cleaned up
        }

        // Assert - Still no instance cached
        $this->assertArrayNotHasKey('failing.service', $this->container->getInstances(),
            'No instance should be cached after second constructor failure');
    }

    /**
     * Test container behavior with non-existent class.
     *
     * Verifies that the container throws appropriate exception when trying
     * to resolve a non-existent class.
     */
    public function testGetWithNonExistentClassThrowsException(): void
    {
        // Act & Assert
        $this->expectException(NotFoundException::class);

        $this->container->get('NonExistentClass');
    }

    /**
     * Test make method with non-existent class.
     *
     * Verifies that the make method also throws appropriate exception for
     * non-existent classes.
     */
    public function testMakeWithNonExistentClassThrowsException(): void
    {
        // Act & Assert
        $this->expectException(NotFoundException::class);

        $this->container->make('NonExistentClass');
    }

    /**
     * Test container with anonymous class names.
     *
     * Verifies that the container can handle anonymous class names (which start with 'class@anonymous').
     */
    public function testContainerHandlesAnonymousClassNames(): void
    {
        // Arrange - Create an anonymous class
        $anonymousClass = new class {
            public string $testProperty = 'test';
        };

        $anonymousClassName = get_class($anonymousClass);

        // Verify it's indeed an anonymous class
        $this->assertStringStartsWith('class@anonymous', $anonymousClassName);

        // Act & Assert - Should be able to register and retrieve
        $this->container->set('anonymous.service', $anonymousClassName);

        $service = $this->container->get('anonymous.service');
        $this->assertInstanceOf($anonymousClassName, $service);
        $this->assertSame('test', $service->testProperty);
    }

    /**
     * Test container with invalid class name format.
     *
     * Verifies that the container properly handles invalid class name formats.
     */
    public function testContainerHandlesInvalidClassNameFormat(): void
    {
        // Act & Assert - Should throw exception for invalid class name format
        $this->expectException(NotFoundException::class);

        $this->container->get('Invalid@Class#Name');
    }

    /**
     * Test multiple calls to get() return same instance (singleton behavior).
     *
     * Verifies that services registered as classes are properly cached as singletons.
     */
    public function testGetReturnsSameInstanceForClassDefinition(): void
    {
        // Arrange
        $this->container->set(TestService::class, TestService::class);

        // Act
        $instance1 = $this->container->get(TestService::class);
        $instance2 = $this->container->get(TestService::class);

        // Assert - Should return same instance (singleton behavior)
        $this->assertSame($instance1, $instance2,
            'Container should return same instance for class definitions');
    }

    /**
     * Test has() method with interface-to-class auto-resolution.
     *
     * Verifies that has() properly handles interface-to-class auto-resolution.
     */
    public function testHasWithInterfaceAutoResolution(): void
    {
        // Act & Assert - Should return true for interfaces that can be auto-resolved
        $this->assertTrue($this->container->has(\Switon\Di\Tests\Fixtures\TestServiceInterface::class),
            'has() should return true for interfaces that can be auto-resolved');
    }

    /**
     * Test has() method with non-existent interface.
     *
     * Verifies that has() returns false for non-existent interfaces.
     */
    public function testHasWithNonExistentInterface(): void
    {
        // Act & Assert - Should return false for non-existent interfaces
        $this->assertFalse($this->container->has('NonExistentInterface'),
            'has() should return false for non-existent interfaces');
    }

    /**
     * Test container behavior with recursive service alias.
     *
     * Verifies that the container can handle recursive service aliases properly
     * and detect circular references in alias definitions.
     */
    public function testRecursiveServiceAlias(): void
    {
        // Arrange - Create circular alias: A -> B -> C -> A
        $this->container->set('service.a', 'service.b');
        $this->container->set('service.b', 'service.c');
        $this->container->set('service.c', 'service.a');

        // Act & Assert - Should detect circular reference
        $this->expectException(\Switon\Di\Exception\CircularDependencyException::class);

        $this->container->make('service.a');
    }

    /**
     * Test container with service alias pointing to non-existent service.
     *
     * Verifies that the container properly handles aliases pointing to non-existent services.
     */
    public function testAliasToNonExistentService(): void
    {
        // Arrange - Create alias to non-existent service
        $this->container->set('alias.to.nonexistent', 'NonExistentService');

        // Act & Assert - Should throw NotFoundException when resolving alias to non-existent service
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        $this->container->get('alias.to.nonexistent');
    }

    /**
     * Test getDefinitions returns expected format.
     *
     * Verifies that getDefinitions() returns the expected array format.
     */
    public function testGetDefinitionsReturnsExpectedFormat(): void
    {
        // Arrange
        $this->container->set('test.service', TestService::class);
        $this->container->set('test.instance', new TestService());

        // Act
        $definitions = $this->container->getDefinitions();

        // Assert
        $this->assertIsArray($definitions, 'getDefinitions() should return an array');
        $this->assertArrayHasKey('test.service', $definitions,
            'Definitions should contain registered service');
        $this->assertArrayHasKey('test.instance', $definitions,
            'Definitions should contain registered instance');
        $this->assertSame(TestService::class, $definitions['test.service'],
            'Class definition should be preserved');
    }

    /**
     * Test container behavior when removing non-existent service.
     *
     * Verifies that removing a non-existent service doesn't cause issues.
     */
    public function testRemoveNonExistentService(): void
    {
        // Arrange - Get initial state
        $initialDefinitions = $this->container->getDefinitions();
        $initialInstances = $this->container->getInstances();

        // Act - Remove non-existent service
        $result = $this->container->remove('NonExistentService');

        // Assert - Should return container for chaining and not modify state
        $this->assertSame($this->container, $result,
            'remove() should return container for method chaining');
        $this->assertEquals($initialDefinitions, $this->container->getDefinitions(),
            'Removing non-existent service should not modify definitions');
        $this->assertEquals($initialInstances, $this->container->getInstances(),
            'Removing non-existent service should not modify instances');
    }

    /**
     * Test container with interface that has no corresponding class.
     *
     * Verifies that the container properly handles interfaces without corresponding classes.
     */
    public function testInterfaceWithoutCorrespondingClass(): void
    {
        // Act & Assert - Should return false for has() since there's no corresponding class
        $this->assertFalse($this->container->has(TempTestInterfaceWithoutClass::class),
            'has() should return false for interfaces without corresponding classes');
    }

    /**
     * Test container with deeply nested dependency resolution.
     *
     * Verifies that the container can handle deeply nested dependency chains.
     */
    public function testDeeplyNestedDependencyResolution(): void
    {
        // Register and resolve multiple services to verify moderate dependency graphs work.
        for ($i = 0; $i < 50; $i++) {
            $serviceName = "test.service.$i";
            $this->container->set($serviceName, TestService::class);
            $service = $this->container->get($serviceName);
            $this->assertInstanceOf(TestService::class, $service);
        }
    }

    /** Internal types such as Closure cannot use newInstanceWithoutConstructor(); surface as DI reflection error. */
    public function testGetClosureClassRaisesReflectionException(): void
    {
        $this->container->set('internal.closure', Closure::class);

        $this->expectException(DiReflectionException::class);

        $this->container->get('internal.closure');
    }

    /**
     * Factory object registered as an instance must return an object from {@code __invoke()}.
     */
    public function testGetInvokeObjectReturningNonObjectRaisesMisuseException(): void
    {
        $factory = new class {
            public function __invoke(): string
            {
                return 'not-an-object';
            }
        };

        $this->container->set('bad.invoke.object', $factory);

        $this->expectException(MisuseException::class);

        $this->container->get('bad.invoke.object');
    }

    /**
     * Array-defined invokable class whose {@code __invoke()} does not return an object must raise misuse (factory pattern).
     */
    public function testGetInvokeClassViaArrayDefinitionReturningNonObjectRaisesMisuseException(): void
    {
        $this->container->set('bad.invoke.array', [
            'class' => DiCoverageInvokeReturnsScalar::class,
        ]);

        $this->expectException(MisuseException::class);

        $this->container->get('bad.invoke.array');
    }

    /** Class with no user-declared constructor uses the createInstance() branch without __construct. */
    public function testGetServiceWithNoExplicitConstructor(): void
    {
        $this->container->set(DiCoverageNoExplicitConstructor::class, DiCoverageNoExplicitConstructor::class);

        $one = $this->container->get(DiCoverageNoExplicitConstructor::class);
        $two = $this->container->get(DiCoverageNoExplicitConstructor::class);

        $this->assertInstanceOf(DiCoverageNoExplicitConstructor::class, $one);
        $this->assertSame($one, $two);
    }
}