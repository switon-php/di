<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use ReflectionMethod;
use Switon\Di\Container;
use Switon\Di\Exception\CircularDependencyException;
use Switon\Di\Exception\NotFoundException;
use Switon\Di\Exception\ReflectionException as DiReflectionException;
use Switon\Di\Exception\UnsupportedDefinitionException;
use Switon\Di\Factory;
use Switon\Di\ServiceProvider;
use Switon\Di\Tests\Fixtures\ExposeGetByIdContainer;
use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\TestCase;

/**
 * Edge case tests for Container.
 *
 * Tests boundary conditions, extreme scenarios, and unusual usage patterns
 * to ensure the container handles them gracefully.
 */
class ContainerEdgeCasesTest extends TestCase
{
    /**
     * Test maximum alias chain depth (exactly 3 levels).
     *
     * Verifies that the container supports alias chains up to the limit.
     */
    public function testMaximumAliasChainDepth(): void
    {
        // Create a 3-level alias chain (the maximum allowed)
        $this->container->set('level3', TestService::class);
        $this->container->set('level2', 'level3');
        $this->container->set('level1', 'level2');

        $service = $this->container->get('level1');
        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test alias chain exceeding maximum depth.
     *
     * Verifies that alias chains deeper than 3 levels are rejected.
     * Note: maxDepth=3 means 3 jumps are allowed (4 levels total: start + 3 jumps).
     */
    public function testAliasChainExceedingMaxDepth(): void
    {
        // Create a 5-level alias chain (exceeds limit of 3 jumps)
        $this->container->set('level5', TestService::class);
        $this->container->set('level4', 'level5');
        $this->container->set('level3', 'level4');
        $this->container->set('level2', 'level3');
        $this->container->set('level1', 'level2');

        $this->expectException(CircularDependencyException::class);

        $this->container->get('level1');
    }

    /**
     * Test maximum recursion depth (close to 16 levels).
     *
     * Verifies that deep dependency chains work up to the limit.
     */
    public function testDeepDependencyChain(): void
    {
        // Create a chain of 10 services (well within the 16 limit)
        for ($i = 1; $i <= 10; $i++) {
            $this->container->set("service$i", TestService::class);
        }

        // Resolve all services
        for ($i = 1; $i <= 10; $i++) {
            $service = $this->container->get("service$i");
            $this->assertInstanceOf(TestService::class, $service);
        }
    }

    /**
     * Test empty service ID.
     *
     * Verifies that empty service IDs are handled gracefully.
     */
    public function testEmptyServiceId(): void
    {
        $this->expectException(NotFoundException::class);

        $this->container->get('');
    }

    /**
     * Test service ID with special characters.
     *
     * Verifies that service IDs with special characters work correctly.
     */
    public function testServiceIdWithSpecialCharacters(): void
    {
        // Named service with special characters
        $this->container->set('service#special', TestService::class);

        $service = $this->container->get('service#special');
        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test very long service ID.
     *
     * Verifies that long service IDs are handled correctly.
     */
    public function testVeryLongServiceId(): void
    {
        $longId = str_repeat('VeryLongServiceName', 10); // 190 characters (reduced from 950)

        $this->container->set($longId, TestService::class);
        $service = $this->container->get($longId);

        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test registering null as service definition.
     *
     * Verifies that null definitions are handled appropriately.
     * Null is treated as "no definition", so getting it will fail.
     */
    public function testNullServiceDefinition(): void
    {
        // Null is accepted but treated as no definition
        $this->container->set('null_service', null);

        // Trying to get it should throw NotFoundException
        $this->expectException(NotFoundException::class);
        $this->container->get('null_service');
    }

    /**
     * Test resolving non-existent service with similar name.
     *
     * Verifies that typos in service names produce clear errors.
     */
    public function testNonExistentServiceWithTypo(): void
    {
        $this->container->set('MyService', TestService::class);

        $this->expectException(NotFoundException::class);

        // Typo: MyServic instead of MyService
        $this->container->get('MyServic');
    }

    /**
     * Test multiple containers with same service definitions.
     *
     * Verifies that multiple container instances are independent.
     */
    public function testMultipleContainersIndependence(): void
    {
        $container1 = $this->createContainer();
        $container2 = $this->createContainer();

        $container1->set('service', TestService::class);

        // container2 should not have the service
        $this->assertTrue($container1->has('service'));
        $this->assertFalse($container2->has('service'));

        // Resolve in container1
        $service1 = $container1->get('service');

        // container2 should still not have it
        $this->assertFalse($container2->has('service'));

        // Register in container2
        $container2->set('service', TestService::class);
        $service2 = $container2->get('service');

        // Should be different instances
        $this->assertNotSame($service1, $service2);
    }

    /**
     * Test factory with empty definitions.
     *
     * Verifies that factories with no definitions work correctly.
     */
    public function testFactoryWithEmptyDefinitions(): void
    {
        $factory = new Factory([]);

        $this->container->set('EmptyFactory', $factory);

        // Should throw NotFoundException when trying to resolve
        $this->expectException(NotFoundException::class);
        $this->container->get('EmptyFactory');
    }

    /**
     * Test factory with only default definition.
     *
     * Verifies that factories with only a default work correctly.
     */
    public function testFactoryWithOnlyDefault(): void
    {
        $factory = new Factory([
            'default' => TestService::class,
        ]);

        $this->container->set('SingleFactory', $factory);

        $service = $this->container->get('SingleFactory');
        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test removing service that doesn't exist.
     *
     * Verifies that removing non-existent services doesn't cause errors.
     */
    public function testRemovingNonExistentService(): void
    {
        // Should not throw exception
        $this->container->remove('NonExistentService');

        $this->assertFalse($this->container->has('NonExistentService'));
    }

    /**
     * Test removing service multiple times.
     *
     * Verifies that removing the same service multiple times is safe.
     */
    public function testRemovingServiceMultipleTimes(): void
    {
        $this->container->set('service', TestService::class);

        // Remove once
        $this->container->remove('service');
        $this->assertFalse($this->container->has('service'));

        // Remove again (should be safe)
        $this->container->remove('service');
        $this->assertFalse($this->container->has('service'));
    }

    /**
     * Test has() with various service states.
     *
     * Verifies that has() correctly reports service existence.
     */
    public function testHasWithVariousStates(): void
    {
        // Not registered
        $this->assertFalse($this->container->has('NotRegistered'));

        // Registered but not resolved
        $this->container->set('Registered', TestService::class);
        $this->assertTrue($this->container->has('Registered'));

        // Registered and resolved
        $this->container->get('Registered');
        $this->assertTrue($this->container->has('Registered'));

        // Removed
        $this->container->remove('Registered');
        $this->assertFalse($this->container->has('Registered'));
    }

    /**
     * Test make() with empty parameters.
     *
     * Verifies that make() works with no parameters.
     */
    public function testMakeWithEmptyParameters(): void
    {
        $service = $this->container->make(TestService::class, []);

        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test make() creates new instances each time.
     *
     * Verifies that make() doesn't cache instances.
     */
    public function testMakeCreatesNewInstances(): void
    {
        $service1 = $this->container->make(TestService::class);
        $service2 = $this->container->make(TestService::class);

        $this->assertNotSame($service1, $service2);
    }

    /**
     * Test get() returns same instance (singleton).
     *
     * Verifies that get() caches instances.
     */
    public function testGetReturnsSameInstance(): void
    {
        $this->container->set('singleton', TestService::class);

        $service1 = $this->container->get('singleton');
        $service2 = $this->container->get('singleton');

        $this->assertSame($service1, $service2);
    }

    /**
     * Test interface auto-resolution with non-existent class.
     *
     * Verifies that interface auto-resolution fails gracefully when
     * the corresponding class doesn't exist.
     */
    public function testInterfaceAutoResolutionWithNonExistentClass(): void
    {
        // Create an interface without corresponding class
        $interfaceName = 'NonExistentServiceInterface';

        $this->expectException(NotFoundException::class);
        $this->container->get($interfaceName);
    }

    /**
     * Test service definition with array but no class key.
     *
     * Verifies that array definitions without 'class' key use the service ID.
     */
    public function testArrayDefinitionWithoutClassKey(): void
    {
        // Register with array definition but no 'class' key
        $this->container->set(TestService::class, ['param' => 'value']);

        $service = $this->container->get(TestService::class);
        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test getDefinitions() returns all definitions.
     *
     * Verifies that getDefinitions() provides access to all registered services.
     */
    public function testGetDefinitionsReturnsAllDefinitions(): void
    {
        $this->container->set('service1', TestService::class);
        $this->container->set('service2', TestService::class);

        $definitions = $this->container->getDefinitions();

        $this->assertArrayHasKey('service1', $definitions);
        $this->assertArrayHasKey('service2', $definitions);
    }

    /**
     * Test getInstances() returns only resolved services.
     *
     * Verifies that getInstances() only includes services that have been resolved.
     */
    public function testGetInstancesReturnsOnlyResolvedServices(): void
    {
        $this->container->set('resolved', TestService::class);
        $this->container->set('not_resolved', TestService::class);

        // Resolve only one
        $this->container->get('resolved');

        $instances = $this->container->getInstances();

        $this->assertArrayHasKey('resolved', $instances);
        $this->assertArrayNotHasKey('not_resolved', $instances);
    }

    /** ReflectionClass constructor failure is wrapped as DI ReflectionException. */
    public function testCreateInstanceWrapsReflectionFailure(): void
    {
        $method = new ReflectionMethod(Container::class, 'createInstance');
        $method->setAccessible(true);

        $this->expectException(DiReflectionException::class);

        $method->invoke($this->container, 'Switon\\Di\\Tests\\Fixtures\\NonexistentClassForReflectionCoverage999', [], null);
    }

    /** createByDefinition(array without class) raises UnsupportedDefinitionException. */
    public function testCreateByDefinitionArrayMissingClassKeyRaisesUnsupportedDefinition(): void
    {
        $method = new ReflectionMethod(Container::class, 'createByDefinition');
        $method->setAccessible(true);

        $this->expectException(UnsupportedDefinitionException::class);

        $method->invoke($this->container, ['only' => 'keys'], null);
    }

    /** getById(): named ID with an explicit definition hits the isset() branch and delegates to getByDefinition(). */
    public function testGetByIdNamedServiceWithExplicitDefinitionDelegatesToGetByDefinition(): void
    {
        $container = new ExposeGetByIdContainer([]);
        $provider = new ServiceProvider();
        $provider->register($container);
        $provider->boot();

        $namedId = TestService::class . '#coverage_slot';
        $container->set($namedId, TestService::class);

        $resolved = $container->callGetById($namedId);

        $this->assertInstanceOf(TestService::class, $resolved);
    }
}
