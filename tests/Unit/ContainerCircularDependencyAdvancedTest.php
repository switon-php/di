<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Factory;
use Switon\Di\Tests\TestCase;

/**
 * Tests for advanced circular dependency scenarios that should be supported.
 *
 * These tests verify that legitimate circular dependency patterns (like factory
 * registration followed by immediate resolution) work correctly.
 */
class ContainerCircularDependencyAdvancedTest extends TestCase
{
    /**
     * Test that factory registration pattern works (like HTTP Server).
     *
     * This pattern: $container->set(Interface::class, new Factory($definitions));
     *               return $container->get(Interface::class);
     * Should work without being blocked as circular dependency.
     */
    public function testFactoryRegistrationPatternWorks(): void
    {
        // Simulate the HTTP Server pattern
        $definitions = [
            'default' => '#auto',
            'auto' => ['class' => \stdClass::class],
            'custom' => ['class' => \stdClass::class],
        ];

        // This is the pattern that was failing before
        $this->container->set('TestInterface', new Factory($definitions));
        $result = $this->container->get('TestInterface');

        // Should successfully resolve to the default instance
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    /**
     * Test that legitimate circular dependencies through property injection work.
     */
    public function testPropertyInjectionCircularDependencyWorks(): void
    {
        // This should work - services can reference each other through properties
        $this->container->set(\Switon\Di\Tests\Fixtures\CircularServiceA::class, \Switon\Di\Tests\Fixtures\CircularServiceA::class);
        $this->container->set(\Switon\Di\Tests\Fixtures\CircularServiceB::class, \Switon\Di\Tests\Fixtures\CircularServiceB::class);

        $serviceA = $this->container->get(\Switon\Di\Tests\Fixtures\CircularServiceA::class);
        $serviceB = $this->container->get(\Switon\Di\Tests\Fixtures\CircularServiceB::class);

        $this->assertInstanceOf(\Switon\Di\Tests\Fixtures\CircularServiceA::class, $serviceA);
        $this->assertInstanceOf(\Switon\Di\Tests\Fixtures\CircularServiceB::class, $serviceB);

        // Verify circular references work
        $this->assertSame($serviceB, $serviceA->serviceB);
        $this->assertSame($serviceA, $serviceB->serviceA);
    }

    /**
     * Test that complex factory patterns with multiple levels work.
     */
    public function testComplexFactoryPatternWorks(): void
    {
        // Create a more complex factory pattern
        $definitions = [
            'default' => '#level1',
            'level1' => '#level2',
            'level2' => ['class' => \stdClass::class],
        ];

        $this->container->set('ComplexInterface', new Factory($definitions));
        $result = $this->container->get('ComplexInterface');

        $this->assertInstanceOf(\stdClass::class, $result);
    }

    /**
     * Test that resolution stack is properly cleaned up after exceptions.
     */
    public function testResolutionStackCleanupAfterException(): void
    {
        // Create a factory that throws an exception
        $factory = new class {
            public function __invoke(): object
            {
                throw new \RuntimeException('Factory failed');
            }
        };

        $this->container->set('FailingService', $factory);

        // First call should fail with RuntimeException
        try {
            $this->container->get('FailingService');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Factory failed', $e->getMessage());
        }

        // Second call should also fail with RuntimeException (not recursion depth exceeded)
        // This verifies that the resolution stack was properly cleaned up
        try {
            $this->container->get('FailingService');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Factory failed', $e->getMessage());
        } catch (\Switon\Di\Exception\CircularDependencyException $e) {
            $this->fail('Resolution stack was not cleaned up properly - got recursion depth exception');
        }
    }
}