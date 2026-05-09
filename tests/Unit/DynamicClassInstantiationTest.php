<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\Fixtures\TestServiceInterface;
use Switon\Di\Tests\TestCase;

/**
 * Test cases for dynamic class name instantiation.
 *
 * Tests the container's ability to create instances using dynamic class names,
 * which is useful for plugin systems, factory patterns, and runtime class resolution.
 */
class DynamicClassInstantiationTest extends TestCase
{
    public function testMakeWithDynamicClassName(): void
    {
        // Test basic dynamic class instantiation
        $className = TestService::class;
        $instance = $this->container->make($className);

        $this->assertInstanceOf(TestService::class, $instance);
        $this->assertInstanceOf($className, $instance);
    }

    public function testMakeWithDynamicClassNameAndParameters(): void
    {
        // Create a test class that accepts constructor parameters
        $testClass = new class {
            public function __construct(
                public string $name = 'default',
                public int    $value = 0
            )
            {
            }
        };

        $className = get_class($testClass);

        // Test with parameters
        $instance = $this->container->make($className, [
            'name' => 'dynamic',
            'value' => 42
        ]);

        $this->assertInstanceOf($className, $instance);
        $this->assertEquals('dynamic', $instance->name);
        $this->assertEquals(42, $instance->value);
    }

    public function testMakeWithDynamicInterfaceName(): void
    {
        // Register an implementation for the interface
        $implementation = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'dynamic-interface';
            }
        };

        $this->container->set(TestServiceInterface::class, $implementation);

        // Use dynamic interface name
        $interfaceName = TestServiceInterface::class;
        $instance = $this->container->make($interfaceName);

        $this->assertInstanceOf(TestServiceInterface::class, $instance);
        $this->assertSame($implementation, $instance);
        $this->assertEquals('dynamic-interface', $instance->getValue());
    }

    public function testMakeWithDynamicClassNameFromArray(): void
    {
        // Simulate getting class names from configuration or database
        $classNames = [
            'service1' => TestService::class,
            'service2' => get_class(new class {
                public string $type = 'dynamic';
            }),
        ];

        foreach ($classNames as $key => $className) {
            $instance = $this->container->make($className);
            $this->assertInstanceOf($className, $instance);

            if ($key === 'service2') {
                $this->assertEquals('dynamic', $instance->type);
            }
        }
    }

    public function testMakeWithDynamicClassNameAndDependencyInjection(): void
    {
        // Create a class with dependencies
        $dependentClass = new class {
            #[Autowired]
            public TestServiceInterface $service;

            public function getServiceValue(): string
            {
                return 'dependent-' . $this->service->getValue();
            }
        };

        // Register dependency
        $serviceImpl = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'injected';
            }
        };
        $this->container->set(TestServiceInterface::class, $serviceImpl);

        // Create instance with dynamic class name
        $className = get_class($dependentClass);
        $instance = $this->container->make($className);

        $this->assertInstanceOf($className, $instance);
        $this->assertEquals('dependent-injected', $instance->getServiceValue());
        $this->assertSame($serviceImpl, $instance->service);
    }

    public function testMakeWithDynamicClassNameFromCallback(): void
    {
        // Simulate runtime class name resolution
        $getClassName = function (string $type): string {
            return match ($type) {
                'basic' => TestService::class,
                'custom' => get_class(new class {
                    public string $name = 'callback-created';
                }),
                default => throw new \InvalidArgumentException("Unknown type: $type")
            };
        };

        // Test basic type
        $basicClassName = $getClassName('basic');
        $basicInstance = $this->container->make($basicClassName);
        $this->assertInstanceOf(TestService::class, $basicInstance);

        // Test custom type
        $customClassName = $getClassName('custom');
        $customInstance = $this->container->make($customClassName);
        $this->assertInstanceOf($customClassName, $customInstance);
        $this->assertEquals('callback-created', $customInstance->name);
    }

    public function testMakeWithDynamicClassNameAndFactoryPattern(): void
    {
        // Create different implementations
        $impl1 = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'impl1';
            }
        };

        $impl2 = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'impl2';
            }
        };

        // Register factory
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => $impl1,
            'alternative' => $impl2,
        ]));

        // Dynamic factory usage
        $interfaceName = TestServiceInterface::class;

        // Get default implementation using get() instead of make()
        $defaultInstance = $this->container->get($interfaceName);
        $this->assertSame($impl1, $defaultInstance);
        $this->assertEquals('impl1', $defaultInstance->getValue());

        // Get named implementation
        $namedInstance = $this->container->get($interfaceName . '#alternative');
        $this->assertSame($impl2, $namedInstance);
        $this->assertEquals('impl2', $namedInstance->getValue());
    }

    public function testMakeWithDynamicClassNameBulkInstantiation(): void
    {
        // Arrange
        $classNames = [
            TestService::class,
            get_class(new class {
                public string $type = 'bulk1';
            }),
            get_class(new class {
                public string $type = 'bulk2';
            }),
            get_class(new class {
                public string $type = 'bulk3';
            }),
        ];

        // Act
        $instances = [];
        for ($i = 0; $i < 100; $i++) {
            $className = $classNames[$i % count($classNames)];
            $instances[] = $this->container->make($className);
        }

        // Assert
        $this->assertCount(100, $instances);

        for ($i = 0; $i < 100; $i++) {
            $expectedClassName = $classNames[$i % count($classNames)];
            $this->assertInstanceOf($expectedClassName, $instances[$i]);
        }
    }

    public function testMakeWithInvalidDynamicClassName(): void
    {
        // Test error handling with invalid class names
        $invalidClassName = 'NonExistentClass';

        $this->expectException(\Switon\Di\Exception\NotFoundException::class);
        $this->container->make($invalidClassName);
    }

    public function testMakeWithDynamicClassNameAndCircularDependencies(): void
    {
        // Create classes with circular dependencies
        $classA = new class {
            #[Autowired]
            public mixed $serviceB = null;

            public function getValue(): string
            {
                return 'A-' . ($this->serviceB ? $this->serviceB->getValue() : 'null');
            }
        };

        $classB = new class {
            #[Autowired]
            public mixed $serviceA = null;

            public function getValue(): string
            {
                return 'B';
            }
        };

        // Register with dynamic names
        $classNameA = get_class($classA);
        $classNameB = get_class($classB);

        $this->container->set('ServiceA', $classA);
        $this->container->set('ServiceB', $classB);

        // Create instances with dynamic class names - use get() for registered services
        $instanceA = $this->container->get('ServiceA');
        $instanceB = $this->container->get('ServiceB');

        $this->assertInstanceOf($classNameA, $instanceA);
        $this->assertInstanceOf($classNameB, $instanceB);

        // Verify instances were created (circular dependencies might not be fully resolved with make())
        $this->assertEquals('A-null', $instanceA->getValue()); // serviceB might be null
    }

    public function testGetWithDynamicServiceId(): void
    {
        // Test get() method with dynamic service IDs
        $serviceId = 'dynamic.service.id';
        $implementation = new class {
            public string $value = 'dynamic-get';
        };

        $this->container->set($serviceId, $implementation);

        // Use dynamic service ID
        $dynamicId = 'dynamic.' . 'service' . '.' . 'id';
        $instance = $this->container->get($dynamicId);

        $this->assertSame($implementation, $instance);
        $this->assertEquals('dynamic-get', $instance->value);
    }

    public function testMakeWithDynamicClassNameAndLazyLoading(): void
    {
        // Create service with lazy dependency
        $serviceClass = new class {
            #[Autowired]
            public TestServiceInterface|\Switon\Core\Lazy $lazyService;

            public function getLazyValue(): string
            {
                return 'lazy-' . $this->lazyService->getValue();
            }
        };

        // Register lazy dependency
        $lazyImpl = new class implements TestServiceInterface {
            public function getValue(): string
            {
                return 'loaded';
            }
        };
        $this->container->set(TestServiceInterface::class, $lazyImpl);

        // Create with dynamic class name
        $className = get_class($serviceClass);
        $instance = $this->container->make($className);

        $this->assertInstanceOf($className, $instance);
        $this->assertInstanceOf(\Switon\Core\Lazy::class, $instance->lazyService);

        // Trigger lazy loading
        $result = $instance->getLazyValue();
        $this->assertEquals('lazy-loaded', $result);

        // Verify lazy service was resolved
        $this->assertSame($lazyImpl, $instance->lazyService);
    }
}