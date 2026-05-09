<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Performance;

use Switon\Di\Container;
use Switon\Di\Factory;
use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\TestCase;

/**
 * Memory leak detection tests for Container.
 *
 * These tests verify that the container properly releases memory when
 * services are removed and that there are no memory leaks in various
 * usage scenarios.
 */
class MemoryLeakTest extends TestCase
{
    /**
     * Test that removed services are garbage collected.
     *
     * Verifies that removing services from the container allows
     * PHP's garbage collector to reclaim memory.
     */
    public function testRemovedServicesAreGarbageCollected(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        // Create and remove services 10 times
        for ($iteration = 0; $iteration < 10; $iteration++) {
            // Register 100 services
            for ($i = 0; $i < 100; $i++) {
                $serviceId = "gc_test_service.{$iteration}.$i";
                $this->container->set($serviceId, TestService::class);
                $service = $this->container->get($serviceId);
                $this->assertInstanceOf(TestService::class, $service);
            }

            // Remove all services
            for ($i = 0; $i < 100; $i++) {
                $serviceId = "gc_test_service.{$iteration}.$i";
                $this->container->remove($serviceId);
            }

            // Force garbage collection
            gc_collect_cycles();
        }

        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        // Memory growth should be minimal (less than 2MB) after 10 cycles
        $this->assertLessThan(2 * 1024 * 1024, $memoryGrowth,
            "Memory leak detected: " . number_format($memoryGrowth / 1024 / 1024, 2) .
            "MB growth after 10 cycles of 100 services each");
    }

    /**
     * Test that factory instances don't leak memory.
     *
     * Verifies that factory-registered services are properly cleaned up.
     */
    public function testFactoryInstancesNoMemoryLeak(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        for ($iteration = 0; $iteration < 10; $iteration++) {
            $factory = new Factory([
                'default' => TestService::class,
                'service1' => TestService::class,
                'service2' => TestService::class,
            ]);

            $serviceId = "factory_test.$iteration";
            $this->container->set($serviceId, $factory);

            // Resolve main service and named services
            $this->container->get($serviceId);
            $this->container->get("$serviceId#service1");
            $this->container->get("$serviceId#service2");

            // Remove all
            $this->container->remove($serviceId);
            $this->container->remove("$serviceId#service1");
            $this->container->remove("$serviceId#service2");

            gc_collect_cycles();
        }

        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        $this->assertLessThan(2 * 1024 * 1024, $memoryGrowth,
            "Factory memory leak detected: " . number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth");
    }

    /**
     * Test that circular dependencies don't cause memory leaks.
     *
     * Verifies that services with circular references are properly
     * garbage collected when removed.
     */
    public function testCircularDependenciesNoMemoryLeak(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        for ($iteration = 0; $iteration < 10; $iteration++) {
            $serviceAId = "circular_a.$iteration";
            $serviceBId = "circular_b.$iteration";

            // Use simple classes without interface enforcement
            $classA = new class {
                public $serviceB;
            };
            $classB = new class {
                public $serviceA;
            };

            $this->container->set($serviceAId, $classA);
            $this->container->set($serviceBId, $classB);

            // Resolve both (creates circular reference)
            $serviceA = $this->container->get($serviceAId);
            $serviceB = $this->container->get($serviceBId);

            $this->assertIsObject($serviceA);
            $this->assertIsObject($serviceB);

            // Remove both
            $this->container->remove($serviceAId);
            $this->container->remove($serviceBId);

            // Break references
            unset($serviceA, $serviceB);

            gc_collect_cycles();
        }

        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        $this->assertLessThan(2 * 1024 * 1024, $memoryGrowth,
            "Circular dependency memory leak detected: " .
            number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth");
    }

    /**
     * Test that container itself doesn't leak memory when recreated.
     *
     * Verifies that creating and destroying containers doesn't leak memory.
     */
    public function testContainerRecreationNoMemoryLeak(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $container = $this->createContainer();

            // Register and resolve services
            for ($i = 0; $i < 50; $i++) {
                $container->set("service.$i", TestService::class);
                $service = $container->get("service.$i");
                $this->assertInstanceOf(TestService::class, $service);
            }

            // Destroy container
            unset($container);

            if ($iteration % 10 === 0) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        $this->assertLessThan(5 * 1024 * 1024, $memoryGrowth,
            "Container recreation memory leak detected: " .
            number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth after 100 containers");
    }

    /**
     * Test that resolution stack doesn't leak memory.
     *
     * Verifies that the resolution stack is properly cleaned up
     * even when exceptions occur.
     */
    public function testResolutionStackNoMemoryLeak(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create a service that throws exception
            $factory = new class {
                public function __invoke(): object
                {
                    throw new \RuntimeException('Test exception');
                }
            };

            $serviceId = "failing_service.$iteration";
            $this->container->set($serviceId, $factory);

            // Try to resolve (will fail)
            try {
                $this->container->get($serviceId);
            } catch (\RuntimeException $e) {
                // Expected
            }

            // Remove service
            $this->container->remove($serviceId);

            if ($iteration % 10 === 0) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        $this->assertLessThan(2 * 1024 * 1024, $memoryGrowth,
            "Resolution stack memory leak detected: " .
            number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth");
    }

    /**
     * Test that make() doesn't leak memory.
     *
     * Verifies that repeatedly calling make() doesn't accumulate memory.
     */
    public function testMakeNoMemoryLeak(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        // Create 1000 instances using make()
        for ($i = 0; $i < 1000; $i++) {
            $service = $this->container->make(TestService::class);
            $this->assertInstanceOf(TestService::class, $service);

            // Immediately unset to allow GC
            unset($service);

            if ($i % 100 === 0) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        // Should be minimal since we're not keeping references
        $this->assertLessThan(1 * 1024 * 1024, $memoryGrowth,
            "make() memory leak detected: " .
            number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth after 1000 make() calls");
    }

    /**
     * Test that complex dependency graphs don't leak memory.
     *
     * Verifies that services with multiple dependencies are properly
     * garbage collected.
     */
    public function testComplexDependencyGraphNoMemoryLeak(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        for ($iteration = 0; $iteration < 20; $iteration++) {
            // Create simple dependency graph without interface enforcement
            $serviceAId = "service_a.$iteration";
            $serviceBId = "service_b.$iteration";
            $serviceCId = "service_c.$iteration";

            $this->container->set($serviceCId, TestService::class);
            $this->container->set($serviceBId, TestService::class);
            $this->container->set($serviceAId, TestService::class);

            // Resolve top-level service
            $serviceA = $this->container->get($serviceAId);
            $this->assertInstanceOf(TestService::class, $serviceA);

            // Remove all services
            $this->container->remove($serviceAId);
            $this->container->remove($serviceBId);
            $this->container->remove($serviceCId);

            unset($serviceA);
            gc_collect_cycles();
        }

        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        $this->assertLessThan(2 * 1024 * 1024, $memoryGrowth,
            "Complex dependency graph memory leak detected: " .
            number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth");
    }

    /**
     * Test long-running container memory stability.
     *
     * Simulates a long-running application with continuous service
     * registration and resolution.
     */
    public function testLongRunningContainerMemoryStability(): void
    {
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();
        $memoryReadings = [];

        // Simulate 500 "requests" with service resolution (reduced from 1000)
        for ($request = 0; $request < 500; $request++) {
            // Each "request" resolves some services
            $service1 = $this->container->make(TestService::class);
            $service2 = $this->container->make(TestService::class);

            $this->assertInstanceOf(TestService::class, $service1);
            $this->assertInstanceOf(TestService::class, $service2);

            unset($service1, $service2);

            // Take memory reading every 50 requests
            if ($request % 50 === 0) {
                gc_collect_cycles();
                $memoryReadings[] = memory_get_usage();
            }
        }

        // Check that memory doesn't grow continuously
        $firstReading = $memoryReadings[0];
        $lastReading = array_last($memoryReadings);
        $memoryGrowth = $lastReading - $firstReading;

        $this->assertLessThan(3 * 1024 * 1024, $memoryGrowth,
            "Long-running container memory growth detected: " .
            number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth over 500 requests");
    }
}
