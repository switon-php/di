<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\LazyPropertyProxy;
use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface, TestServiceWithMethod};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for LazyPropertyProxy class.
 */
class LazyPropertyProxyTest extends TestCase
{
    /**
     * Creates a LazyPropertyProxy for testing.
     *
     * @param object $testObject The test object containing the property
     * @param string $propertyName The name of the property
     * @param string $type The service type to resolve
     * @param string|null $value Optional explicit service ID or relative reference
     * @return LazyPropertyProxy The created proxy instance
     */
    protected function createProxy(object $testObject, string $propertyName, string $type, ?string $value = null): LazyPropertyProxy
    {
        $property = new \ReflectionProperty($testObject, $propertyName);
        return new LazyPropertyProxy($this->container, $property, $testObject, $type, $value);
    }

    public function testLazyPropertyProxyCallWithNotFoundException(): void
    {
        // Arrange
        $testObject = new class {
            public TestServiceInterface|\Switon\Core\Lazy $service;
        };
        $proxy = $this->createProxy($testObject, 'service', TestServiceInterface::class, 'non.existent.service');
        $testObject->service = $proxy;
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        // Act - Call method to trigger resolution, which will fail
        $proxy->someMethod();

        // Assert - Exception is expected (via expectException)
    }

    public function testLazyPropertyProxyCallWithRelativeReferenceNotFound(): void
    {
        // Arrange
        $testObject = new class {
            public TestServiceInterface|\Switon\Core\Lazy $service;
        };
        $proxy = $this->createProxy($testObject, 'service', TestServiceInterface::class, '#nonexistent');
        $testObject->service = $proxy;
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        // Act - Call method to trigger resolution, which will fail
        $proxy->someMethod();

        // Assert - Exception is expected (via expectException)
    }

    /**
     * Helper method to assert lazy property proxy resolves correctly via method call.
     *
     * **Lazy Resolution Behavior (Core Feature, Not Implementation Detail):**
     *
     * When a method is called on the proxy (via __call), the proxy follows this documented process:
     * 1. Resolves the actual service from the container
     * 2. **Replaces itself in the property with the resolved service** (core feature for performance)
     * 3. Forwards the method call to the resolved service
     * 4. Returns the result from the actual service method
     *
     * **Property Replacement is a Core Feature:**
     * The property replacement behavior is explicitly documented in LazyPropertyProxy class:
     * - "The resolved service replaces the proxy in the property" (class doc)
     * - "Replaces the proxy in the property with the resolved service" (__call() doc)
     * - "Subsequent calls go directly to the resolved service (no proxy overhead)" (performance doc)
     *
     * This replacement is essential for:
     * - **Performance**: Avoids proxy overhead on subsequent calls
     * - **Memory**: Prevents memory leaks from keeping proxy references
     * - **Correctness**: Ensures subsequent calls use the actual service instance
     *
     * **Testing Strategy:**
     * This test verifies both the method call result AND the property replacement behavior,
     * as both are core features of LazyPropertyProxy. If this behavior changes, it would
     * be a breaking change requiring documentation updates.
     *
     * @param object $testObject The test object with the property
     * @param string $propertyName The property name
     * @param object $expectedService The expected resolved service
     * @param string $methodName The method to call on the proxy
     * @param mixed $expectedResult The expected method result
     */
    protected function assertProxyResolvesViaCall(
        object $testObject,
        string $propertyName,
        object $expectedService,
        string $methodName,
        mixed  $expectedResult
    ): void
    {
        // Assert: Before method call, property contains the proxy (Lazy instance)
        $this->assertInstanceOf(\Switon\Core\Lazy::class, $testObject->{$propertyName});

        // Act: Call method on proxy - this triggers resolution and replaces proxy with actual service
        // This replacement is a core feature documented in LazyPropertyProxy class
        $result = $testObject->{$propertyName}->{$methodName}();

        // Assert: Method returns expected result
        $this->assertEquals($expectedResult, $result);

        // Assert: After method call, property contains the actual service (not proxy)
        // This verifies the core feature: proxy replaces itself in the property after first resolution.
        // This is documented behavior, not an implementation detail, and is essential for:
        // - Performance (subsequent calls bypass proxy)
        // - Memory management (proxy is replaced, not kept)
        // - Correctness (subsequent calls use actual service instance)
        $this->assertSame($expectedService, $testObject->{$propertyName});
    }

    public function testLazyPropertyProxyCallWithExplicitServiceId(): void
    {
        // Arrange
        $service = new TestServiceWithMethod();
        $this->container->set('custom.service.id', $service);

        $testObject = new class {
            public TestServiceInterface|\Switon\Core\Lazy $service;
        };

        $proxy = $this->createProxy($testObject, 'service', TestServiceInterface::class, 'custom.service.id');
        $testObject->service = $proxy;

        // Act & Assert (verified via helper method)
        $this->assertProxyResolvesViaCall($testObject, 'service', $service, 'testMethod', 'test');
    }

    public function testLazyPropertyProxyCallWithPropertyNameAliasNotFound(): void
    {
        // Arrange
        $defaultService = new TestServiceWithMethod();
        $this->container->set(TestServiceInterface::class, $defaultService);

        $testObject = new class {
            public TestServiceInterface|\Switon\Core\Lazy $custom;
        };

        $proxy = $this->createProxy($testObject, 'custom', TestServiceInterface::class, null);
        $testObject->custom = $proxy;

        // Act & Assert (verified via helper method)
        // Property name alias doesn't exist, should fall back to type
        $this->assertProxyResolvesViaCall($testObject, 'custom', $defaultService, 'testMethod', 'test');
    }

    public function testLazyPropertyProxyWithDirectType(): void
    {
        // Arrange
        $serviceWithMethod = new class {
            public function testMethod(): string
            {
                return 'resolved';
            }
        };
        $this->container->set(TestService::class, $serviceWithMethod);

        $testObject = new class {
            public $service = null;
        };

        $proxy = $this->createProxy($testObject, 'service', TestService::class, null);
        $testObject->service = $proxy;

        // Act & Assert (verified via helper method)
        $this->assertProxyResolvesViaCall($testObject, 'service', $serviceWithMethod, 'testMethod', 'resolved');
    }

    public function testLazyPropertyProxyWithExplicitServiceIdResolvesCorrectly(): void
    {
        $serviceWithMethod = new class {
            public function testMethod(): string
            {
                return 'resolved';
            }
        };
        $this->container->set('custom.service', $serviceWithMethod);

        $testObject = new class {
            public $service = null;
        };

        $proxy = $this->createProxy($testObject, 'service', TestService::class, 'custom.service');
        $testObject->service = $proxy;

        $this->assertProxyResolvesViaCall($testObject, 'service', $serviceWithMethod, 'testMethod', 'resolved');
    }

    public function testLazyPropertyProxyWithRelativeReference(): void
    {
        $defaultService = new TestService();
        $customService = new TestServiceWithMethod();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => $defaultService,
            'custom' => $customService,
        ]));

        $testObject = new class {
            public TestServiceInterface|\Switon\Core\Lazy $service;
        };

        $proxy = $this->createProxy($testObject, 'service', TestServiceInterface::class, '#custom');
        $testObject->service = $proxy;

        $this->assertProxyResolvesViaCall($testObject, 'service', $customService, 'testMethod', 'test');
    }

    public function testLazyPropertyProxyWithPropertyNameAlias(): void
    {
        $customService = new TestServiceWithMethod();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => new TestService(),
            'custom' => $customService,
        ]));

        $testObject = new class {
            public TestServiceInterface|\Switon\Core\Lazy $custom;
        };

        $proxy = $this->createProxy($testObject, 'custom', TestServiceInterface::class, null);
        $testObject->custom = $proxy;

        $this->assertProxyResolvesViaCall($testObject, 'custom', $customService, 'testMethod', 'test');
    }

    /**
     * Data provider for lazy property proxy __get tests.
     *
     * @return array<string, array{serviceId: string, value: string|null, propertyName: string, type: string, expectedServiceClass: string, setupFactory: bool, factoryName: string|null}>
     */
    public static function lazyPropertyProxyGetDataProvider(): array
    {
        return [
            'explicit service id' => [
                'custom.service.id',
                'custom.service.id',
                'service',
                TestServiceInterface::class,
                TestServiceWithMethod::class,
                false,
                null,
            ],
            'relative reference' => [
                TestServiceInterface::class,
                '#custom',
                'service',
                TestServiceInterface::class,
                TestServiceWithMethod::class,
                true,
                'custom',
            ],
            'property name alias' => [
                TestServiceInterface::class,
                null,
                'custom',
                TestServiceInterface::class,
                TestServiceWithMethod::class,
                true,
                'custom',
            ],
            'property name alias fallback' => [
                TestServiceInterface::class,
                null,
                'custom',
                TestServiceInterface::class,
                TestServiceInterface::class,
                false,
                null,
            ],
        ];
    }

    public function testLazyPropertyProxyGetExplicitServiceId(): void
    {
        // Test that accessing non-existent property throws exception
        $this->runLazyPropertyProxyGetTest(
            'custom.service.id',
            'custom.service.id',
            'service',
            TestServiceInterface::class,
            TestServiceWithMethod::class,
            false,
            null
        );
    }

    public function testLazyPropertyProxyGetRelativeReference(): void
    {
        // Test that accessing non-existent property throws exception
        $this->runLazyPropertyProxyGetTest(
            TestServiceInterface::class,
            '#custom',
            'service',
            TestServiceInterface::class,
            TestServiceWithMethod::class,
            true,
            'custom'
        );
    }

    public function testLazyPropertyProxyGetPropertyNameAlias(): void
    {
        // Test that accessing non-existent property throws exception
        $this->runLazyPropertyProxyGetTest(
            TestServiceInterface::class,
            null,
            'custom',
            TestServiceInterface::class,
            TestServiceWithMethod::class,
            true,
            'custom'
        );
    }

    public function testLazyPropertyProxyGetPropertyNameAliasFallback(): void
    {
        // Test that accessing non-existent property throws exception
        $this->runLazyPropertyProxyGetTest(
            TestServiceInterface::class,
            null,
            'custom',
            TestServiceInterface::class,
            TestServiceInterface::class,
            false,
            null
        );
    }

    public function testLazyPropertyProxyGetWithExistingProperty(): void
    {
        // Test accessing a property that exists on the resolved service
        // Create a service class with properties for testing
        $service = new class implements TestServiceInterface {
            public string $name = 'TestName';
            public int $value = 42;
        };
        $this->container->set(TestServiceInterface::class, $service);

        $testObject = new class {
            public TestServiceInterface|\Switon\Core\Lazy $service;
        };

        $proxy = $this->createProxy($testObject, 'service', TestServiceInterface::class, null);
        $testObject->service = $proxy;

        // Access properties on the proxy - should resolve service and forward property access
        // Note: Accessing property directly via $proxy->name triggers __get
        $name = $testObject->service->name;
        $this->assertSame('TestName', $name);

        $value = $testObject->service->value;
        $this->assertSame(42, $value);

        // Verify service was resolved and replaced in property
        $this->assertSame($service, $testObject->service);
    }

    protected function runLazyPropertyProxyGetTest(
        string  $serviceId,
        ?string $value,
        string  $propertyName,
        string  $type,
        string  $expectedServiceClass,
        bool    $setupFactory,
        ?string $factoryName
    ): void
    {
        // Setup service based on test case
        if ($setupFactory) {
            $customService = new TestServiceWithMethod();
            $this->container->set($serviceId, new \Switon\Di\Factory([
                'default' => new TestService(),
                'custom' => $customService,
            ]));
            $expectedService = $customService;
        } else {
            if ($expectedServiceClass === TestServiceWithMethod::class) {
                $expectedService = new TestServiceWithMethod();
                $this->container->set($value ?? $serviceId, $expectedService);
            } else {
                // Property name alias fallback case - use default service
                $expectedService = new TestServiceWithMethod();
                $this->container->set($serviceId, $expectedService);
            }
        }

        $testObject = new class($propertyName) {
            public $service;
            public $custom;

            public function __construct(public string $propName)
            {
            }
        };

        $proxy = $this->createProxy($testObject, $propertyName, $type, $value);
        $testObject->{$propertyName} = $proxy;

        $this->assertInstanceOf(\Switon\Core\Lazy::class, $testObject->{$propertyName});

        // Test that accessing a non-existent property throws an exception
        $this->expectException(\Error::class);

        // Trigger __get by accessing the property - should throw exception
        $_ = $testObject->{$propertyName}->{$propertyName};
    }

}

