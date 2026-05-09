<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Tests\Fixtures\{CircularServiceA, CircularServiceB};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container circular dependency handling.
 *
 * Tests circular dependency resolution, exception handling, and path reporting.
 */
class ContainerCircularDependencyTest extends TestCase
{
    public function testCircularDependencyCanBeResolved(): void
    {
        // Arrange
        // $this->container->set(CircularServiceA::class, CircularServiceA::class);
        // $this->container->set(CircularServiceB::class, CircularServiceB::class);

        // Act
        // Circular dependency is supported via instance caching before property injection
        $serviceA = $this->container->get(CircularServiceA::class);
        $serviceB = $this->container->get(CircularServiceB::class);

        // Assert
        $this->assertInstanceOf(CircularServiceA::class, $serviceA,
            'CircularServiceA should be created successfully');
        $this->assertInstanceOf(CircularServiceB::class, $serviceB,
            'CircularServiceB should be created successfully');
    }

    public function testCircularDependencyReferencesSameInstances(): void
    {
        // Arrange
        // $this->container->set(CircularServiceA::class, CircularServiceA::class);
        // $this->container->set(CircularServiceB::class, CircularServiceB::class);

        // Act
        $serviceA = $this->container->get(CircularServiceA::class);
        $serviceB = $this->container->get(CircularServiceB::class);

        // Assert - Verify circular reference exists and points to same instances
        $injectedB = $serviceA->serviceB;
        $injectedA = $serviceB->serviceA;

        $this->assertInstanceOf(CircularServiceB::class, $injectedB,
            'CircularServiceA should have CircularServiceB injected');
        $this->assertInstanceOf(CircularServiceA::class, $injectedA,
            'CircularServiceB should have CircularServiceA injected');
        $this->assertSame($serviceA, $injectedA, 'Circular references should point to same instances');
        $this->assertSame($serviceB, $injectedB, 'Circular references should point to same instances');
    }

    /**
     * Test that CircularDependencyException is thrown when definition resolution creates a cycle.
     *
     * This happens when service definitions form a chain that loops back to itself,
     * e.g., A -> B -> C -> A, where each service is defined as another service.
     */
    public function testCircularDependencyExceptionThrownForDefinitionCycle(): void
    {
        // Arrange - Create a circular definition chain: ServiceA -> ServiceB -> ServiceA
        $this->container->set('ServiceA', 'ServiceB');
        $this->container->set('ServiceB', 'ServiceA');

        // Act & Assert
        $this->expectException(\Switon\Di\Exception\CircularDependencyException::class);

        // Attempting to resolve ServiceA should detect the cycle
        $this->container->make('ServiceA');
    }

    /**
     * Test that CircularDependencyException includes the dependency path in the message.
     */
    public function testCircularDependencyExceptionIncludesPath(): void
    {
        // Arrange - Create a longer cycle: A -> B -> C -> A
        $this->container->set('ServiceA', 'ServiceB');
        $this->container->set('ServiceB', 'ServiceC');
        $this->container->set('ServiceC', 'ServiceA');

        // Act & Assert
        try {
            $this->container->make('ServiceA');
            $this->fail('Expected CircularDependencyException was not thrown');
        } catch (\Switon\Di\Exception\CircularDependencyException $e) {
            $message = $e->getMessage();
            // Verify the path includes key services from the cycle (but don't verify exact format)
            $this->assertStringContainsString('ServiceA', $message,
                'Exception message should include ServiceA from the cycle');
            $this->assertStringContainsString('ServiceB', $message,
                'Exception message should include ServiceB from the cycle');
        }
    }
}

