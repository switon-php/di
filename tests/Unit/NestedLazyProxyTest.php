<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Lazy;
use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\Fixtures\TestServiceInterface;
use Switon\Di\Tests\TestCase;

/**
 * Test cases for nested lazy loading scenarios.
 *
 * Tests basic lazy loading patterns that are actually supported.
 */
class NestedLazyProxyTest extends TestCase
{
    public function testBasicLazyLoading(): void
    {
        // Create service with lazy dependency
        $service = new class {
            #[Autowired]
            public TestServiceInterface|Lazy $lazyService;

            public function getValue(): string
            {
                return $this->lazyService->getValue();
            }
        };

        // Create the actual service
        $actualService = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'lazy-loaded';
            }
        };

        // Register service
        $this->container->set(TestServiceInterface::class, $actualService);

        // Inject dependencies
        $this->injector->inject($service);

        // Verify lazy proxy is created
        $this->assertInstanceOf(Lazy::class, $service->lazyService);

        // Trigger resolution
        $result = $service->getValue();
        $this->assertEquals('lazy-loaded', $result);

        // Verify proxy was replaced with actual service
        $this->assertSame($actualService, $service->lazyService);
    }

    public function testLazyLoadingWithFactory(): void
    {
        // Create service with lazy dependency on factory-registered service
        $service = new class {
            #[Autowired]
            public TestServiceInterface|Lazy $factoryService;

            public function getFactoryValue(): string
            {
                return $this->factoryService->getValue();
            }
        };

        // Create factory services
        $defaultService = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'factory-default';
            }
        };

        // Register factory
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => $defaultService,
        ]));

        // Inject dependencies
        $this->injector->inject($service);

        // Verify lazy proxy is created
        $this->assertInstanceOf(Lazy::class, $service->factoryService);

        // Trigger resolution - should resolve to default
        $result = $service->getFactoryValue();
        $this->assertEquals('factory-default', $result);

        // Verify proxy was replaced with actual service
        $this->assertSame($defaultService, $service->factoryService);
    }

    public function testLazyLoadingWithExistingInstance(): void
    {
        // Pre-register and resolve service
        $existingService = new TestService();
        $this->container->set(TestService::class, $existingService);
        $resolvedService = $this->container->get(TestService::class);

        // Create service with lazy dependency
        $service = new class {
            #[Autowired]
            public TestService|Lazy $lazyService;
        };

        // Inject dependencies
        $this->injector->inject($service);

        // If Lazy is declared, always create proxy (even if service is already resolved)
        $lazyService = $service->lazyService;
        $this->assertInstanceOf(Lazy::class, $lazyService);

        // Note: TestService is an empty class with no methods or properties,
        // so proxy resolution cannot be triggered via normal access.
        // The test verifies that proxy is created even when service is already resolved.
        // This demonstrates that Lazy declaration takes precedence over service resolution state.
    }

    public function testLazyLoadingWorksForMultipleServices(): void
    {
        // Arrange
        $services = [];
        for ($i = 0; $i < 10; $i++) {
            $services[] = new class {
                #[Autowired]
                public TestServiceInterface|Lazy $lazyService;

                public function getValue(): string
                {
                    return $this->lazyService->getValue();
                }
            };
        }

        $actualService = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'bulk-test';
            }
        };

        $this->container->set(TestServiceInterface::class, $actualService);

        // Act & Assert
        foreach ($services as $service) {
            $this->injector->inject($service);

            $result = $service->getValue();
            $this->assertSame('bulk-test', $result);
            $this->assertSame($actualService, $service->lazyService);
        }
    }

    public function testLazyLoadingWithPropertyAccess(): void
    {
        // Create service with lazy dependency
        $service = new class {
            #[Autowired]
            public TestServiceInterface|Lazy $lazyService;
        };

        // Create the actual service with a property
        $actualService = new class implements TestServiceInterface {
            public string $testProperty = 'property-value';

            public function getValue(): string
            {
                return 'service-value';
            }
        };

        // Register service
        $this->container->set(TestServiceInterface::class, $actualService);

        // Inject dependencies
        $this->injector->inject($service);

        // Verify lazy proxy is created
        $this->assertInstanceOf(Lazy::class, $service->lazyService);

        // Access property through proxy (should trigger resolution)
        $propertyValue = $service->lazyService->testProperty;
        $this->assertEquals('property-value', $propertyValue);

        // Verify proxy was replaced with actual service
        $this->assertSame($actualService, $service->lazyService);
    }
}