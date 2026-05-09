<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Integration;

use Switon\Di\Container;
use Switon\Di\Tests\Fixtures\{ServiceA,
    ServiceB,
    ServiceC,
    ServiceWithMultipleDependencies,
    TestDependency,
    TestService
};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for complex dependency injection scenarios.
 *
 * Tests complex object graphs, deep dependency chains, and multiple dependency scenarios.
 */
class ComplexDependencyInjectionTest extends TestCase
{
    /**
     * Test deep dependency chain resolution.
     *
     * Verifies that services with deep dependency chains (A -> B -> C -> D)
     * can be resolved correctly.
     */
    public function testDeepDependencyChainResolution(): void
    {
        // Arrange - Explicitly register the complex graph services
        $this->container->set(ServiceA::class, ServiceA::class);
        $this->container->set(ServiceB::class, ServiceB::class);
        $this->container->set(ServiceC::class, ServiceC::class);
        $this->container->set(TestService::class, TestService::class);

        // Act
        $serviceA = $this->container->get(ServiceA::class);

        // Assert - Verify the entire chain is properly resolved
        $this->assertInstanceOf(ServiceA::class, $serviceA);
        $this->assertInstanceOf(ServiceB::class, $serviceA->serviceB);
        $this->assertInstanceOf(ServiceC::class, $serviceA->serviceB->serviceC);
        $this->assertInstanceOf(TestService::class, $serviceA->serviceB->serviceC->testService);

        // Verify names to ensure correct instances
        $this->assertSame('ServiceA', $serviceA->name);
        $this->assertSame('ServiceB', $serviceA->serviceB->name);
        $this->assertSame('ServiceC', $serviceA->serviceB->serviceC->name);
    }

    /**
     * Test multiple dependencies resolution.
     *
     * Verifies that services with multiple dependencies can be resolved correctly.
     */
    public function testMultipleDependenciesResolution(): void
    {
        // Arrange - Explicitly register services
        $this->container->set(ServiceWithMultipleDependencies::class, ServiceWithMultipleDependencies::class);
        $this->container->set(ServiceA::class, ServiceA::class);
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(TestDependency::class, TestDependency::class);

        // Act
        $complexService = $this->container->get(ServiceWithMultipleDependencies::class);

        // Assert - Verify all dependencies are injected
        $this->assertInstanceOf(ServiceWithMultipleDependencies::class, $complexService);
        $this->assertInstanceOf(ServiceA::class, $complexService->serviceA);
        $this->assertInstanceOf(TestService::class, $complexService->testService);
        $this->assertInstanceOf(TestDependency::class, $complexService->testDependency);
    }

    /**
     * Test complex dependency graph with shared dependencies.
     *
     * Verifies that shared dependencies are properly resolved as singletons
     * in complex dependency graphs.
     */
    public function testSharedDependenciesInComplexGraph(): void
    {
        // Arrange - Explicitly register services
        $this->container->set(ServiceA::class, ServiceA::class);
        $this->container->set(ServiceB::class, ServiceB::class);
        $this->container->set(ServiceC::class, ServiceC::class);
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(ServiceWithMultipleDependencies::class, ServiceWithMultipleDependencies::class);

        // Act - Get multiple services that depend on TestService
        $serviceA = $this->container->get(ServiceA::class);
        $complexService = $this->container->get(ServiceWithMultipleDependencies::class);

        // Assert - TestService should be the same instance in both chains
        $this->assertSame(
            $serviceA->serviceB->serviceC->testService,
            $complexService->testService,
            'Shared dependency should be the same instance across different dependency chains'
        );
    }

    /**
     * Test container performance with complex dependency graph.
     *
     * Verifies that the container can handle complex dependency graphs
     * without significant performance degradation.
     */
    public function testComplexGraphCanBeResolvedMultipleTimes(): void
    {
        // Arrange - Explicitly register services
        $this->container->set(ServiceA::class, ServiceA::class);
        $this->container->set(ServiceB::class, ServiceB::class);
        $this->container->set(ServiceC::class, ServiceC::class);
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(ServiceWithMultipleDependencies::class, ServiceWithMultipleDependencies::class);

        // Act & Assert
        for ($i = 0; $i < 10; $i++) {
            $serviceA = $this->container->get(ServiceA::class);
            $complexService = $this->container->get(ServiceWithMultipleDependencies::class);

            $this->assertInstanceOf(ServiceA::class, $serviceA);
            $this->assertInstanceOf(ServiceWithMultipleDependencies::class, $complexService);
        }
    }

    /**
     * Test complex dependency graph with array configurations.
     *
     * Verifies that complex dependency graphs work correctly with array configurations.
     */
    public function testComplexGraphWithArrayConfigurations(): void
    {
        // Arrange - Set up services with array configurations
        $this->container->set(ServiceA::class, [
            'class' => ServiceA::class,
        ]);
        $this->container->set(ServiceB::class, [
            'class' => ServiceB::class,
        ]);
        $this->container->set(ServiceC::class, [
            'class' => ServiceC::class,
        ]);
        $this->container->set(TestService::class, [
            'class' => TestService::class,
        ]);

        // Act
        $serviceA = $this->container->get(ServiceA::class);

        // Assert - Complex graph should resolve correctly with array configs
        $this->assertInstanceOf(ServiceA::class, $serviceA);
        $this->assertInstanceOf(ServiceB::class, $serviceA->serviceB);
        $this->assertInstanceOf(ServiceC::class, $serviceA->serviceB->serviceC);
        $this->assertInstanceOf(TestService::class, $serviceA->serviceB->serviceC->testService);
    }

    /**
     * Test complex dependency graph after container serialization/deserialization.
     *
     * Verifies that complex dependency graphs remain functional after container state changes.
     */
    public function testComplexGraphAfterSerialization(): void
    {
        // Arrange - Explicitly register to test definition transfer between containers
        $this->container->set(ServiceA::class, ServiceA::class);
        $this->container->set(ServiceB::class, ServiceB::class);
        $this->container->set(ServiceC::class, ServiceC::class);
        $this->container->set(TestService::class, TestService::class);

        // Resolve services to populate container instances
        $originalServiceA = $this->container->get(ServiceA::class);

        // Create a new container with the same definitions
        $newContainer = new Container($this->container->getDefinitions());

        // Resolve the same service from new container
        $newServiceA = $newContainer->get(ServiceA::class);

        // Assert - Both containers should resolve to valid complex graphs
        $this->assertInstanceOf(ServiceA::class, $originalServiceA);
        $this->assertInstanceOf(ServiceA::class, $newServiceA);

        // The services should be different instances since they come from different containers
        $this->assertNotSame($originalServiceA, $newServiceA);

        // But both should have valid dependency chains
        $this->assertInstanceOf(ServiceB::class, $originalServiceA->serviceB);
        $this->assertInstanceOf(ServiceB::class, $newServiceA->serviceB);
        $this->assertInstanceOf(ServiceC::class, $originalServiceA->serviceB->serviceC);
        $this->assertInstanceOf(ServiceC::class, $newServiceA->serviceB->serviceC);
    }
}