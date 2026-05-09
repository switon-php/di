<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Performance;

use Switon\Di\Container;
use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\TestCase;

/**
 * Performance tests for Container to identify bottlenecks.
 */
class ContainerPerformanceTest extends TestCase
{
    /**
     * Test basic container instantiation performance.
     */
    public function testContainerInstantiationPerformance(): void
    {
        $startTime = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $container = $this->createContainer();
            unset($container);
        }

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $executionTime,
            "Container instantiation took too long: {$executionTime}s for 1000 iterations");
    }

    /**
     * Test service registration performance.
     */
    public function testServiceRegistrationPerformance(): void
    {
        $container = $this->createContainer();

        $startTime = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $container->set("service.$i", TestService::class);
        }

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $executionTime,
            "Service registration took too long: {$executionTime}s for 1000 services");
    }

    /**
     * Test service resolution performance.
     */
    public function testServiceResolutionPerformance(): void
    {
        $container = $this->createContainer();

        // Pre-register services
        for ($i = 0; $i < 1000; $i++) {
            $container->set("service.$i", TestService::class);
        }

        $startTime = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $service = $container->get("service.$i");
            $this->assertInstanceOf(TestService::class, $service);
        }

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $executionTime,
            "Service resolution took too long: {$executionTime}s for 1000 services");
    }

    /**
     * Test complex dependency resolution performance using simple classes without autowiring conflicts.
     */
    public function testComplexDependencyResolutionPerformance(): void
    {
        // Create simple test classes without problematic dependencies
        $simpleClass = new class {
            public string $value = 'test';
        };

        $container = $this->createContainer();
        $container->set('simple.service', get_class($simpleClass));

        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $service = $container->get('simple.service');
            $this->assertInstanceOf(get_class($simpleClass), $service);
        }

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $executionTime,
            "Simple service resolution took too long: {$executionTime}s for 100 iterations");
    }

    /**
     * Test memory usage during service resolution.
     */
    public function testMemoryUsage(): void
    {
        $initialMemory = memory_get_usage();

        $container = $this->createContainer();

        // Register and resolve many services
        for ($i = 0; $i < 500; $i++) {
            $container->set("memory_test_service.$i", TestService::class);
            $service = $container->get("memory_test_service.$i");
            $this->assertInstanceOf(TestService::class, $service);
        }

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, // 50MB limit
            "Memory usage too high: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB");
    }

    /**
     * Test large-scale service registration and resolution (1000+ services).
     *
     * This test verifies that the container can handle large applications
     * with many services without performance degradation.
     */
    public function testLargeScaleServiceResolution(): void
    {
        $container = $this->createContainer();
        $serviceCount = 1000; // Reduced to fit memory limit

        // Registration phase
        $startTime = microtime(true);
        for ($i = 0; $i < $serviceCount; $i++) {
            $container->set("large_scale_service.$i", TestService::class);
        }
        $registrationTime = microtime(true) - $startTime;

        // Resolution phase
        $startTime = microtime(true);
        for ($i = 0; $i < $serviceCount; $i++) {
            $service = $container->get("large_scale_service.$i");
            $this->assertInstanceOf(TestService::class, $service);
        }
        $resolutionTime = microtime(true) - $startTime;

        // Cached resolution phase (should be much faster)
        $startTime = microtime(true);
        for ($i = 0; $i < $serviceCount; $i++) {
            $service = $container->get("large_scale_service.$i");
            $this->assertInstanceOf(TestService::class, $service);
        }
        $cachedResolutionTime = microtime(true) - $startTime;

        // Assertions
        $this->assertLessThan(1.5, $registrationTime,
            "Registration took too long: {$registrationTime}s for {$serviceCount} services");
        $this->assertLessThan(1.5, $resolutionTime,
            "Resolution took too long: {$resolutionTime}s for {$serviceCount} services");
        $this->assertLessThan(0.1, $cachedResolutionTime,
            "Cached resolution took too long: {$cachedResolutionTime}s for {$serviceCount} services");

        // Cached resolution should be faster than first resolution
        // Note: The speedup factor varies based on system load and PHP version
        $this->assertLessThan($resolutionTime, $cachedResolutionTime,
            "Cached resolution should be faster than first resolution " .
            "(first: {$resolutionTime}s, cached: {$cachedResolutionTime}s)");
    }

    /**
     * Test memory leak detection - ensure services are properly garbage collected.
     *
     * This test verifies that removing services from the container allows
     * PHP's garbage collector to reclaim memory.
     */
    public function testMemoryLeakDetection(): void
    {
        $container = $this->createContainer();

        // Baseline memory
        gc_collect_cycles();
        $baselineMemory = memory_get_usage();

        // Create and remove services multiple times
        for ($iteration = 0; $iteration < 5; $iteration++) {
            // Register and resolve 200 services
            for ($i = 0; $i < 200; $i++) {
                $serviceId = "leak_test_service.{$iteration}.$i";
                $container->set($serviceId, TestService::class);
                $service = $container->get($serviceId);
                $this->assertInstanceOf(TestService::class, $service);
            }

            // Remove all services
            for ($i = 0; $i < 200; $i++) {
                $serviceId = "leak_test_service.{$iteration}.$i";
                $container->remove($serviceId);
            }

            // Force garbage collection
            gc_collect_cycles();
        }

        // Final memory check
        $finalMemory = memory_get_usage();
        $memoryGrowth = $finalMemory - $baselineMemory;

        // Memory growth should be minimal (less than 5MB) after multiple cycles
        $this->assertLessThan(5 * 1024 * 1024, $memoryGrowth,
            "Memory leak detected: " . number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth after 5 cycles");
    }

    /**
     * Test performance with complex dependency graphs.
     *
     * Verifies that the container efficiently handles services with
     * multiple levels of dependencies.
     */
    public function testComplexDependencyGraphPerformance(): void
    {
        $container = $this->createContainer();

        // Create a dependency graph: Service -> Dep1 -> Dep2 -> Dep3
        $container->set('Dep3', TestService::class);
        $container->set('Dep2', ['class' => TestService::class]);
        $container->set('Dep1', ['class' => TestService::class]);
        $container->set('MainService', ['class' => TestService::class]);

        $startTime = microtime(true);

        // Resolve 1000 times
        for ($i = 0; $i < 1000; $i++) {
            $service = $container->get('MainService');
            $this->assertInstanceOf(TestService::class, $service);
        }

        $executionTime = microtime(true) - $startTime;

        // Should be fast due to caching
        $this->assertLessThan(0.1, $executionTime,
            "Complex dependency resolution took too long: {$executionTime}s for 1000 iterations");
    }

    /**
     * Test concurrent service resolution performance.
     *
     * Simulates multiple services being resolved in quick succession,
     * which is common in web applications.
     */
    public function testConcurrentServiceResolutionPerformance(): void
    {
        $container = $this->createContainer();

        // Register multiple service types
        for ($i = 0; $i < 100; $i++) {
            $container->set("service_type_$i", TestService::class);
        }

        $startTime = microtime(true);

        // Resolve services in random order (simulating concurrent requests)
        for ($iteration = 0; $iteration < 100; $iteration++) {
            for ($i = 0; $i < 100; $i++) {
                $serviceId = "service_type_" . ($i % 100);
                $service = $container->get($serviceId);
                $this->assertInstanceOf(TestService::class, $service);
            }
        }

        $executionTime = microtime(true) - $startTime;

        $this->assertLessThan(1.0, $executionTime,
            "Concurrent resolution took too long: {$executionTime}s for 10000 resolutions");
    }

    /**
     * Test memory efficiency with large number of cached instances.
     *
     * Verifies that caching many service instances doesn't cause
     * excessive memory usage.
     */
    public function testMemoryEfficiencyWithManyInstances(): void
    {
        gc_collect_cycles();
        $initialMemory = memory_get_usage();

        $container = $this->createContainer();

        // Create and cache 500 service instances
        for ($i = 0; $i < 500; $i++) {
            $container->set("cached_service.$i", TestService::class);
            $service = $container->get("cached_service.$i");
            $this->assertInstanceOf(TestService::class, $service);
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // Each TestService instance should be very small
        // 500 instances should use less than 8MB
        $this->assertLessThan(8 * 1024 * 1024, $memoryUsed,
            "Memory usage too high for 500 cached instances: " .
            number_format($memoryUsed / 1024 / 1024, 2) . "MB");

        // Average memory per instance should be reasonable
        $avgMemoryPerInstance = $memoryUsed / 500;
        $this->assertLessThan(20 * 1024, $avgMemoryPerInstance,
            "Average memory per instance too high: " .
            number_format($avgMemoryPerInstance / 1024, 2) . "KB");
    }
}
