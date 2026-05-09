<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Di\Event\SingletonCreated;
use Switon\Di\Exception\NotFoundException;
use Switon\Di\Exception\RedundantAutoMappingException;
use Switon\Di\Exception\ServiceAlreadyResolvedException;
use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface, TestServiceWithParams};
use Switon\Di\Tests\TestCase;
use function get_class;

/**
 * Test cases for Container basic operations.
 *
 * Tests basic service registration and retrieval: set(), get(), has(), remove(),
 * getDefinition(), getDefinitions(), getInstances().
 */
class ContainerBasicTest extends TestCase
{
    /**
     * Test basic service registration and retrieval with class name.
     *
     * Verifies that the set() method correctly registers a service definition
     * and that get() retrieves it. This test specifically validates the set()
     * method's behavior, not auto-resolution.
     */
    public function testSetAndGetWithClassName(): void
    {
        // Arrange - Explicitly testing set() method
        $this->container->set(TestService::class, TestService::class);

        // Act
        $service = $this->container->get(TestService::class);

        // Assert
        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test service registration and retrieval with pre-created instance.
     *
     * Verifies that pre-created instances can be registered and retrieved,
     * and that the same instance is returned (not a copy).
     */
    public function testSetAndGetWithInstance(): void
    {
        // Arrange
        $instance = new TestService();
        $this->container->set(TestService::class, $instance);

        // Act
        $service = $this->container->get(TestService::class);

        // Assert
        $this->assertSame($instance, $service, 'Container should return the same instance that was registered');
    }

    /**
     * Test service registration and retrieval with array configuration.
     *
     * Verifies that services can be registered with array configuration
     * and that configuration values are properly injected into the service.
     */
    public function testSetAndGetWithArrayConfig(): void
    {
        // Arrange
        $this->container->set(TestServiceWithParams::class, [
            'class' => TestServiceWithParams::class,
            'name' => 'TestName',
            'value' => 42,
        ]);

        // Act
        $service = $this->container->get(TestServiceWithParams::class);

        // Assert
        $this->assertInstanceOf(TestServiceWithParams::class, $service);
        $this->assertSame('TestName', $service->name, 'Service name should be injected from array config');
        $this->assertSame(42, $service->value, 'Service value should be injected from array config');
    }

    public function testSetAndGetWithArrayConfigWithoutClassKey(): void
    {
        // Arrange - Array config without 'class' key, should use service ID as class name
        $this->container->set(TestServiceWithParams::class, [
            'name' => 'AutoClass',
            'value' => 100,
        ]);

        // Act
        $service = $this->container->get(TestServiceWithParams::class);

        // Assert
        $this->assertInstanceOf(TestServiceWithParams::class, $service);
        $this->assertSame('AutoClass', $service->name);
        $this->assertSame(100, $service->value);
    }

    public function testSetAndGetWithArrayConfigForNamedService(): void
    {
        // Arrange - Named service with array config without 'class' key
        $serviceId = TestServiceWithParams::class . '#custom';
        $this->container->set($serviceId, [
            'name' => 'NamedService',
            'value' => 200,
        ]);

        // Act
        $service = $this->container->get($serviceId);

        // Assert
        $this->assertInstanceOf(TestServiceWithParams::class, $service);
        $this->assertSame('NamedService', $service->name);
        $this->assertSame(200, $service->value);
    }

    public function testGetThrowsNotFoundExceptionForUnregisteredService(): void
    {
        // Act & Assert
        $this->expectException(NotFoundException::class);

        $this->container->get('NonExistentService');
    }

    public function testRemoveService(): void
    {
        // Arrange - Explicitly register to test remove() method
        $this->container->set(TestService::class, TestService::class);
        $this->assertTrue($this->container->has(TestService::class), 'Service should be registered');

        // Act
        $this->container->remove(TestService::class);

        // Assert
        // After remove, getDefinition() should return null
        // Note: has() may still return true if class exists (auto-resolution),
        // but getDefinition() should return null to confirm removal
        $this->assertNull($this->container->getDefinition(TestService::class),
            'getDefinition() should return null after remove');
        $this->assertFalse(isset($this->container->getInstances()[TestService::class]),
            'Instance should be removed from cache');
    }

    public function testRemoveReturnsContainerForChaining(): void
    {
        // Arrange - Explicitly register to test remove() method
        $this->container->set(TestService::class, TestService::class);

        // Act
        $result = $this->container->remove(TestService::class);

        // Assert
        $this->assertSame($this->container, $result, 'remove() should return container for method chaining');
    }

    public function testSetThrowsExceptionWhenInstanceAlreadyResolved(): void
    {
        // Arrange - Explicitly register to test set() method behavior
        $this->container->set(TestService::class, TestService::class);
        $this->container->get(TestService::class); // Resolve instance

        // Act & Assert
        $this->expectException(ServiceAlreadyResolvedException::class);

        $this->container->set(TestService::class, TestService::class);
    }

    /**
     * Explicit Interface → concrete bindings that mirror built-in auto-mapping must fail fast.
     */
    public function testSetThrowsRedundantAutoMappingWhenBindingDuplicatesConvention(): void
    {
        $this->expectException(RedundantAutoMappingException::class);

        $this->container->set(TestServiceInterface::class, TestService::class);
    }

    /**
     * When a definition is an array without a class key, a later string set() merges in the class name.
     */
    public function testSetMergesStringClassIntoPriorArrayDefinitionWithoutClassKey(): void
    {
        $id = TestServiceWithParams::class;

        $this->container->set($id, [
            'name' => 'Merged',
            'value' => 7,
        ]);

        $this->container->set($id, TestServiceWithParams::class);

        $service = $this->container->get($id);

        $this->assertInstanceOf(TestServiceWithParams::class, $service);
        $this->assertSame('Merged', $service->name);
        $this->assertSame(7, $service->value);
    }

    /** Partial array set() inherits class from a prior definition stored as an array with a class key. */
    public function testSetMergesClassFromPriorArrayDefinitionWithClassKey(): void
    {
        $id = 'svc.merge.array_class';

        $this->container->set($id, [
            'class' => TestServiceWithParams::class,
            'name' => 'First',
            'value' => 1,
        ]);

        $this->container->set($id, [
            'name' => 'Second',
            'value' => 2,
        ]);

        $service = $this->container->get($id);

        $this->assertInstanceOf(TestServiceWithParams::class, $service);
        $this->assertSame('Second', $service->name);
        $this->assertSame(2, $service->value);
    }

    /** Partial array set() inherits class when the prior definition was only a class-name string. */
    public function testSetMergesClassFromPriorPlainStringDefinitionIntoPartialArray(): void
    {
        $id = 'svc.merge.str_then_partial';

        $this->container->set($id, TestServiceWithParams::class);

        $this->container->set($id, [
            'name' => 'AfterPartial',
            'value' => 31,
        ]);

        $service = $this->container->get($id);

        $this->assertInstanceOf(TestServiceWithParams::class, $service);
        $this->assertSame('AfterPartial', $service->name);
        $this->assertSame(31, $service->value);
    }

    public function testReplaceOverridesResolvedService(): void
    {
        $original = new TestService();
        $replacement = new TestService();

        $this->container->set(TestService::class, $original);
        $this->assertSame($original, $this->container->get(TestService::class));

        $result = $this->container->replace(TestService::class, $replacement);
        $this->assertSame($this->container, $result, 'replace() should return container for method chaining');
        $this->assertSame($replacement, $this->container->get(TestService::class));
    }

    public function testHasReturnsTrueForRegisteredInstance(): void
    {
        // Arrange
        $instance = new TestService();
        $this->container->set(TestService::class, $instance);

        // Act & Assert
        $this->assertTrue($this->container->has(TestService::class),
            'has() should return true for registered instance');
    }

    public function testHasReturnsTrueForRegisteredDefinition(): void
    {
        // Arrange - Explicitly register to test has() method with registered definition
        $this->container->set(TestService::class, TestService::class);

        // Act & Assert
        $this->assertTrue($this->container->has(TestService::class),
            'has() should return true for registered definition');
    }

    public function testHasReturnsTrueForFactoryRegisteredNamedService(): void
    {
        // Arrange
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'custom' => TestService::class,
        ]));

        // Act & Assert
        // Factory::register() expands named services into definitions immediately,
        // so has() should return true for Factory-registered named services
        $this->assertTrue($this->container->has(TestServiceInterface::class . '#custom'),
            'has() should return true for Factory-registered named services');
    }

    public function testHasReturnsTrueForInterfaceAutoResolution(): void
    {
        // Act & Assert
        $this->assertTrue($this->container->has(TestServiceInterface::class),
            'has() should return true for interfaces that can be auto-resolved');
    }

    public function testHasReturnsFalseForNonExistentClass(): void
    {
        // Act & Assert
        $this->assertFalse($this->container->has('NonExistentClass'),
            'has() should return false for non-existent classes');
    }

    public function testGetDefinitionReturnsRegisteredDefinition(): void
    {
        // Arrange - Explicitly register to test getDefinition() method
        $definition = TestService::class;
        $this->container->set(TestService::class, $definition);

        // Act
        $result = $this->container->getDefinition(TestService::class);

        // Assert
        $this->assertSame($definition, $result, 'getDefinition() should return the registered definition');
    }

    public function testGetDefinitionReturnsNullForUnregisteredService(): void
    {
        // Act
        $result = $this->container->getDefinition('NonExistentService');

        // Assert
        $this->assertNull($result, 'getDefinition() should return null for unregistered service');
    }

    public function testGetInstancesReturnsAllResolvedInstances(): void
    {
        // Arrange - Use interface for TestService (interface-first principle)
        // TestServiceWithParams doesn't have an interface, so it can be auto-resolved
        // Act
        $this->container->get(TestServiceInterface::class);
        $this->container->get(TestServiceWithParams::class);

        $instances = $this->container->getInstances();

        // Assert
        $this->assertArrayHasKey(TestServiceInterface::class, $instances,
            'getInstances() should include resolved TestServiceInterface');
        $this->assertArrayHasKey(TestServiceWithParams::class, $instances,
            'getInstances() should include resolved TestServiceWithParams');
        $this->assertInstanceOf(TestService::class, $instances[TestServiceInterface::class]);
        $this->assertInstanceOf(TestServiceWithParams::class, $instances[TestServiceWithParams::class]);
    }

    public function testGetDefinitionsReturnsAllDefinitions(): void
    {
        // Arrange - Explicitly register to test getDefinitions() method
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(TestServiceWithParams::class, TestServiceWithParams::class);

        // Act
        $definitions = $this->container->getDefinitions();

        // Assert
        $this->assertArrayHasKey(TestService::class, $definitions,
            'getDefinitions() should include TestService definition');
        $this->assertArrayHasKey(TestServiceWithParams::class, $definitions,
            'getDefinitions() should include TestServiceWithParams definition');
    }

    public function testGetWithCallableDefinitionReturnsNonObjectThrowsException(): void
    {
        // Arrange - Create a callable that returns non-object
        $callable = new class {
            public function __invoke(): string
            {
                return 'not an object';
            }
        };

        $this->container->set(TestService::class, get_class($callable));

        // Act & Assert
        $this->expectException(\Switon\Core\Exception\MisuseException::class);

        $this->container->get(TestService::class);
    }

    /**
     * Test that callable definition creates service correctly.
     *
     * Verifies that when a callable class is used as a definition,
     * it can create service instances successfully.
     */
    public function testGetWithCallableDefinitionCreatesService(): void
    {
        // Arrange - Create a callable that returns an object
        $callable = new class {
            public function __invoke(): object
            {
                return new TestService();
            }
        };

        $callableClass = get_class($callable);
        $this->container->set('service1', $callableClass);

        // Act
        $service = $this->container->get('service1');

        // Assert - Should return TestService instance
        $this->assertInstanceOf(TestService::class, $service);
    }

    /** Registering an invokable object (not a class string) invokes __invoke() and caches the product singleton. */
    public function testGetWithInvokableObjectDefinitionCachesInvokeResult(): void
    {
        $factory = new class {
            public function __invoke(): TestService
            {
                return new TestService();
            }
        };

        $this->container->set('svc.invokable.obj', $factory);

        $one = $this->container->get('svc.invokable.obj');
        $two = $this->container->get('svc.invokable.obj');

        $this->assertInstanceOf(TestService::class, $one);
        $this->assertSame($one, $two);
    }

    /**
     * Test that callable object is cached by class name.
     *
     * Verifies that the callable object itself is cached using its class name
     * as the key, not the service ID.
     */
    public function testCallableObjectIsCachedByClassName(): void
    {
        // Arrange - Create a callable that returns an object
        $callable = new class {
            public function __invoke(): object
            {
                return new TestService();
            }
        };

        $callableClass = get_class($callable);
        $this->container->set('service1', $callableClass);

        // Act
        $this->container->get('service1');

        // Assert - Callable object should be cached by class name
        $instances = $this->container->getInstances();
        $this->assertArrayHasKey($callableClass, $instances, 'Callable object should be cached by class name');
        $this->assertInstanceOf($callableClass, $instances[$callableClass], 'Cached instance should be the callable object');
    }

    /**
     * Test that service instances from callable are cached separately by service ID.
     *
     * Verifies that when the same callable class is used for multiple service IDs,
     * each service ID gets its own cached instance.
     */
    public function testCallableServiceInstancesCachedByServiceId(): void
    {
        // Arrange - Create a callable that returns an object
        $callable = new class {
            public function __invoke(): object
            {
                return new TestService();
            }
        };

        $callableClass = get_class($callable);
        $this->container->set('service1', $callableClass);
        $this->container->set('service2', $callableClass); // Same callable class, different IDs

        // Act
        $service1 = $this->container->get('service1');
        $service2 = $this->container->get('service2');

        // Assert - Both should return TestService instances
        $this->assertInstanceOf(TestService::class, $service1);
        $this->assertInstanceOf(TestService::class, $service2);

        // Verify that service instances are cached separately by their IDs
        $instances = $this->container->getInstances();
        $this->assertArrayHasKey('service1', $instances, 'Service1 should be cached by its ID');
        $this->assertArrayHasKey('service2', $instances, 'Service2 should be cached by its ID');
        $this->assertSame($service1, $instances['service1'], 'Service1 should be cached');
        $this->assertSame($service2, $instances['service2'], 'Service2 should be cached');
    }

    public function testIsResolvedFalseUntilFirstGet(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $this->assertFalse($this->container->isResolved(TestService::class));

        $this->container->get(TestService::class);

        $this->assertTrue($this->container->isResolved(TestService::class));
    }

    /**
     * replace() removes first; when the new binding matches interface→class auto-map convention, set() is skipped.
     */
    public function testReplaceWithAutoMapPairLeavesDefinitionUnset(): void
    {
        $this->container->set(TestServiceInterface::class, TestServiceWithParams::class);
        $this->container->get(TestServiceInterface::class);

        $this->container->replace(TestServiceInterface::class, TestService::class);

        $this->assertNull(
            $this->container->getDefinition(TestServiceInterface::class),
            'Redundant auto-map replace should not re-register an explicit definition'
        );
    }

    public function testGetUnregisteredNamedServiceThrows(): void
    {
        $this->expectException(NotFoundException::class);

        $this->container->get(TestServiceInterface::class . '#not_registered_slot');
    }

    public function testSingletonCreatedDispatchedWhenDispatcherRegistered(): void
    {
        $dispatcher = $this->createEventDispatcherStub();
        $this->container->set(EventDispatcherInterface::class, $dispatcher);
        $this->container->get(EventDispatcherInterface::class);

        $this->container->set(TestService::class, TestService::class);

        $before = count($dispatcher->dispatchedEvents);
        $this->container->get(TestService::class);

        $this->assertGreaterThan($before, count($dispatcher->dispatchedEvents));
        $last = $dispatcher->dispatchedEvents[array_key_last($dispatcher->dispatchedEvents)];
        $this->assertInstanceOf(SingletonCreated::class, $last);
    }
}

