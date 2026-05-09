<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Di\Event\FactoryObjectInjected;
use Switon\Di\Tests\Fixtures\{DiCoverageAmbiguousConcretesHost,
    DiCoverageAmbiguousInterfacesHost,
    DiCoverageConcreteEmailHost,
    DiCoverageIntersectionHost,
    MissingOptionalDependency,
    MissingOptionalDependencyImpl,
    TestDependency,
    TestSecondService,
    TestService,
    TestServiceInterface,
    TestServiceWithArray,
    TestServiceWithArrayMerge,
    TestServiceWithDefault,
    TestServiceWithDependency,
    TestServiceWithInstances,
    TestServiceWithInterface,
    TestServiceWithLazy,
    TestServiceWithMethod,
    TestServiceWithNullable,
    TestServiceWithNullableObject,
    TestServiceWithParams,
    TestServiceWithScalar
};
use Switon\Di\Exception\MissingConfigurationException;
use Switon\Di\Tests\TestCase;

/**
 * Test cases for AutowiredInjector functionality.
 */
class AutowiredInjectorTest extends TestCase
{

    public function testPropertyInjectionWithObjectType(): void
    {
        // Arrange
        $this->container->set(TestDependency::class, TestDependency::class);

        $service = new TestServiceWithDependency();

        // Act
        $this->injector->inject($service);

        // Assert
        $this->assertInstanceOf(TestDependency::class, $service->dependency,
            'Object type property should be injected correctly');
    }

    public function testPropertyInjectionWithScalarType(): void
    {
        // Arrange
        $service = new TestServiceWithScalar();

        // Act
        $this->injector->inject($service, [
            'name' => 'TestName',
            'value' => 42,
        ]);

        // Assert
        $this->assertSame('TestName', $service->name, 'String property should be injected correctly');
        $this->assertSame(42, $service->value, 'Integer property should be injected correctly');
    }

    public function testPropertyInjectionWithArrayType(): void
    {
        // Arrange
        $service = new TestServiceWithArray();
        $expectedItems = ['item1', 'item2', 'item3'];

        // Act
        $this->injector->inject($service, [
            'items' => $expectedItems,
        ]);

        // Assert
        $this->assertSame($expectedItems, $service->items, 'Array type property should be injected correctly');
    }

    public function testPropertyInjectionWithDefaultValue(): void
    {
        // Arrange
        $service = new TestServiceWithDefault();

        // Act
        $this->injector->inject($service);

        // Assert
        $this->assertSame('DefaultName', $service->name, 'Default value should be used when no parameter provided');
    }

    public function testPropertyInjectionOverrideDefaultValue(): void
    {
        // Arrange
        $service = new TestServiceWithDefault();
        $customName = 'CustomName';

        // Act
        $this->injector->inject($service, [
            'name' => $customName,
        ]);

        // Assert
        $this->assertSame($customName, $service->name, 'Parameter should override default value');
    }

    public function testPropertyInjectionWithInterface(): void
    {
        // Arrange
        $service = new TestServiceWithInterface();

        // Act
        $this->injector->inject($service);

        // Assert
        $this->assertInstanceOf(TestService::class, $service->service,
            'Interface type property should resolve to implementation');
    }

    public function testPropertyInjectionWithLazyLoading(): void
    {
        // Arrange
        $serviceWithMethod = new TestServiceWithMethod();
        $this->container->set(TestServiceInterface::class, $serviceWithMethod);

        $service = new TestServiceWithLazy();

        // Act
        $this->injector->inject($service);

        // Assert - Should be a LazyPropertyProxy initially
        $lazyService = $service->service;
        $this->assertInstanceOf(\Switon\Core\Lazy::class, $lazyService,
            'Lazy property should be wrapped in LazyPropertyProxy');

        // Act - First call should resolve and replace proxy
        $result = $lazyService->testMethod();
        $resolvedService = $service->service;

        // Assert - Proxy should be replaced with actual service
        $this->assertSame('test', $result, 'Lazy service method should return expected value');
        $this->assertInstanceOf(TestServiceWithMethod::class, $resolvedService,
            'Lazy service should be resolved to actual instance');
        $this->assertNotInstanceOf(\Switon\Core\Lazy::class, $resolvedService,
            'Proxy should be replaced after first access');
    }

    public function testPropertyInjectionWithInstances(): void
    {
        // Arrange
        $service1 = new TestService();
        $service2 = new TestService();
        $this->container->set('service1', $service1);
        $this->container->set('service2', $service2);

        $service = new TestServiceWithInstances();

        // Act
        $this->injector->inject($service, [
            'services' => ['service1', 'service2'],
        ]);

        // Assert
        $this->assertIsArray($service->services, 'instances: true property should be an array');
        $this->assertCount(2, $service->services, 'Should inject all specified service instances');
        $this->assertSame($service1, $service->services[0], 'First service should match');
        $this->assertSame($service2, $service->services[1], 'Second service should match');
    }

    public function testPropertyInjectionWithArrayMerge(): void
    {
        // Arrange
        $service = new TestServiceWithArrayMerge();

        // Act
        $this->injector->inject($service, [
            'config' => [
                'key2' => 'newvalue2',
                'key3' => 'value3',
            ],
        ]);

        // Assert
        $this->assertSame('value1', $service->config['key1'], 'Default value should be preserved');
        $this->assertSame('newvalue2', $service->config['key2'], 'Parameter should override default');
        $this->assertSame('value3', $service->config['key3'], 'New key should be added');
    }

    public function testPropertyInjectionWithArrayMergeNullRemovesKey(): void
    {
        // Arrange
        $service = new TestServiceWithArrayMerge();

        // Act
        $this->injector->inject($service, [
            'config' => [
                'key1' => null,
                'key3' => 'value3',
            ],
        ]);

        // Assert
        $this->assertArrayNotHasKey('key1', $service->config, 'Null value should remove key from config');
        $this->assertSame('value2', $service->config['key2'], 'Other default values should be preserved');
        $this->assertSame('value3', $service->config['key3'], 'New key should be added');
    }

    public function testPropertyInjectionWithNullableScalarType(): void
    {
        // Nullable scalar types are allowed (configuration values may not exist)
        $service = new TestServiceWithNullable();
        $this->injector->inject($service);

        $this->assertNull($service->optional);
    }

    public function testPropertyInjectionWithNullableObjectTypeStillThrowsWhenServiceMissing(): void
    {
        $service = new TestServiceWithNullableObject();

        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);

        $this->injector->inject($service);
    }

    public function testPropertyInjectionWithNullableObjectTypeStillResolvesWhenServiceExists(): void
    {
        $implementation = new MissingOptionalDependencyImpl();
        $this->container->set(MissingOptionalDependency::class, $implementation);

        $service = new TestServiceWithNullableObject();

        $this->injector->inject($service);

        $this->assertSame($implementation, $service->service);
    }

    public function testPropertyInjectionWithNullableObjectTypeStillThrowsForExplicitMissingReference(): void
    {
        $service = new TestServiceWithNullableObject();

        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);

        $this->injector->inject($service, ['service' => '#missing']);
    }

    public function testPropertyInjectionThrowsMissingTypeDeclarationException(): void
    {
        $service = new class {
            #[Autowired]
            protected $property; // No type declaration
        };

        $this->expectException(\Switon\Di\Exception\MissingTypeDeclarationException::class);

        $this->injector->inject($service);
    }

    public function testPropertyInjectionThrowsMissingConfigurationException(): void
    {
        $service = new class {
            #[Autowired]
            protected string $required; // No default value, no parameter
        };

        $this->expectException(\Switon\Di\Exception\MissingConfigurationException::class);

        $this->injector->inject($service);
    }

    public function testPropertyInjectionThrowsServiceInjectionException(): void
    {
        // Use an interface that doesn't end with 'Interface' or has no corresponding class
        // This ensures the container cannot auto-resolve it
        $service = new class {
            #[Autowired]
            protected \NonExistentInterface $service; // Interface doesn't exist, cannot be auto-resolved
        };

        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);

        $this->injector->inject($service);
    }

    public function testPropertyInjectionWithLazyLoadingExistingInstance(): void
    {
        $service = new TestService();
        $this->container->set(TestServiceInterface::class, $service);
        // Pre-create instance
        $this->container->get(TestServiceInterface::class);

        $testService = new TestServiceWithLazy();
        $this->injector->inject($testService);

        // If Lazy is declared, always create proxy (even if service is already resolved)
        $lazyService = $testService->service;
        $this->assertInstanceOf(\Switon\Core\Lazy::class, $lazyService);

        // Note: TestService is an empty class with no methods or properties,
        // so proxy resolution cannot be triggered via normal access.
        // The test verifies that proxy is created even when service is already resolved.
        // This demonstrates that Lazy declaration takes precedence over service resolution state.
    }

    public function testPropertyInjectionWithInstancesArrayMerge(): void
    {
        $service1 = new TestService();
        $service2 = new TestService();
        $this->container->set('service1', $service1);
        $this->container->set('service2', $service2);

        $service = new TestServiceWithInstances();
        $service->services = [
            0 => 'service1',
        ];

        $this->injector->inject($service, [
            'services' => [
                0 => null, // Remove
                1 => 'service2', // Add
            ],
        ]);

        $this->assertIsArray($service->services);
        $this->assertCount(1, $service->services);
        $this->assertArrayHasKey(1, $service->services);
        $this->assertSame($service2, $service->services[1]);
    }

    public function testPropertyInjectionWithTraversableArray(): void
    {
        $service = new TestServiceWithArray();
        $service->items = [];

        $iterator = new \ArrayIterator(['a' => 1, 'b' => 2]);
        $this->injector->inject($service, [
            'items' => $iterator,
        ]);

        // Traversable should be converted to array
        $this->assertIsArray($service->items);
        $this->assertArrayHasKey('a', $service->items);
        $this->assertArrayHasKey('b', $service->items);
        $this->assertEquals(1, $service->items['a']);
        $this->assertEquals(2, $service->items['b']);
    }

    public function testPropertyInjectionWithArrayEmptyDefault(): void
    {
        $service = new TestServiceWithArray();
        $service->items = [];

        $this->injector->inject($service, [
            'items' => ['a' => 1, 'b' => 2],
        ]);

        // Empty default should be overridden directly
        $this->assertIsArray($service->items);
        $this->assertArrayHasKey('a', $service->items);
        $this->assertArrayHasKey('b', $service->items);
        $this->assertEquals(1, $service->items['a']);
        $this->assertEquals(2, $service->items['b']);
    }

    public function testPropertyInjectionThrowsMissingConfigurationExceptionForInstances(): void
    {
        $service = new class {
            #[Autowired(instances: true)]
            protected array $services;
        };

        $this->expectException(\Switon\Di\Exception\MissingConfigurationException::class);

        $this->injector->inject($service);
    }

    public function testPropertyInjectionThrowsMissingConfigurationExceptionForNonArray(): void
    {
        $service = new class {
            #[Autowired(instances: true)]
            protected array $services = [];
        };

        $this->expectException(\Switon\Di\Exception\MissingConfigurationException::class);

        $this->injector->inject($service, [
            'services' => 'not-array',
        ]);
    }

    public function testPropertyInjectionThrowsServiceInjectionExceptionForInvalidServiceId(): void
    {
        $service = new class {
            #[Autowired(instances: true)]
            protected array $services = ['nonexistent'];
        };

        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);

        $this->injector->inject($service);
    }

    public function testPropertyInjectionWithInstancesInlineDefinition(): void
    {
        // Arrange: inline definition with class and parameters
        $service = new TestServiceWithInstances();

        // Act: use inline array definition (creates new instance via make())
        $this->injector->inject($service, [
            'services' => [
                'inline' => ['class' => TestServiceWithParams::class, 'name' => 'InlineA', 'value' => 42],
            ],
        ]);

        // Assert
        $this->assertCount(1, $service->services);
        $this->assertArrayHasKey('inline', $service->services);
        $this->assertInstanceOf(TestServiceWithParams::class, $service->services['inline']);
        $this->assertSame('InlineA', $service->services['inline']->name);
        $this->assertSame(42, $service->services['inline']->value);
    }

    public function testPropertyInjectionWithInstancesMixedStringAndInline(): void
    {
        // Arrange: mix string IDs (get) and inline definitions (make)
        $cached = new TestService();
        $this->container->set('cached', $cached);

        $service = new TestServiceWithInstances();

        // Act
        $this->injector->inject($service, [
            'services' => [
                'fromContainer' => 'cached',
                'inline' => ['class' => TestService::class],
            ],
        ]);

        // Assert: string uses get() (singleton), inline uses make() (new instance)
        $this->assertCount(2, $service->services);
        $this->assertSame($cached, $service->services['fromContainer']);
        $this->assertInstanceOf(TestService::class, $service->services['inline']);
        $this->assertNotSame($cached, $service->services['inline']);
    }

    public function testPropertyInjectionThrowsWhenInlineDefinitionLacksClassKey(): void
    {
        $service = new class {
            #[Autowired(instances: true)]
            protected array $services;
        };

        $this->expectException(\Switon\Di\Exception\MissingConfigurationException::class);
        $this->expectExceptionMessage('"class" key');

        $this->injector->inject($service, [
            'services' => [
                'bad' => ['format' => '{}'],
            ],
        ]);
    }

    public function testPropertyInjectionThrowsWhenInstancesValueIsInvalidType(): void
    {
        $service = new class {
            #[Autowired(instances: true)]
            protected array $services;
        };

        $this->expectException(\Switon\Di\Exception\MissingConfigurationException::class);
        $this->expectExceptionMessage('expected string');

        $this->injector->inject($service, [
            'services' => [
                'bad' => 123,
            ],
        ]);
    }

    /** Object property: inline array with 'class' → make(that class, params). */
    public function testObjectPropertyInjectionWithInlineArrayWithClass(): void
    {
        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        $this->injector->inject($service, [
            'service' => ['class' => TestServiceWithParams::class, 'name' => 'Inline', 'value' => 100],
        ]);

        $this->assertInstanceOf(TestServiceWithParams::class, $service->service);
        $this->assertSame('Inline', $service->service->name);
        $this->assertSame(100, $service->service->value);
    }

    /** Object property: inline array without 'class' → property type (interface) used, container resolves implementation. */
    public function testObjectPropertyInjectionWithInlineArrayWithoutClassResolvesInterface(): void
    {
        // Override the default Interface → Class auto-map without triggering redundancy:
        // provide an array definition with explicit class.
        $this->container->set(TestServiceInterface::class, [
            'class' => TestServiceWithParams::class,
        ]);

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        $this->injector->inject($service, [
            'service' => ['name' => 'FromInterface', 'value' => 99],
        ]);

        $this->assertInstanceOf(TestServiceWithParams::class, $service->service);
        $this->assertSame('FromInterface', $service->service->name);
        $this->assertSame(99, $service->service->value);
    }

    public function testLazyTypeRequiresTwoTypes(): void
    {
        $service = new class {
            #[Autowired]
            public TestServiceInterface|\Switon\Core\Lazy $service;
        };

        $this->injector->inject($service);

        $this->assertInstanceOf(\Switon\Core\Lazy::class, $service->service);
    }


    public function testPropertyInjectionWithTraversableOverridesDefault(): void
    {
        $service = new class {
            #[Autowired]
            public array $items = ['key1' => 'value1'];
        };

        $iterator = new \ArrayIterator(['key2' => 'value2']);
        $this->injector->inject($service, [
            'items' => $iterator,
        ]);

        // Traversable should override completely, no merging
        $this->assertEquals(['key2' => 'value2'], $service->items);
    }

    public function testPropertyInjectionWithTraversableAndNoDefaultKeepsNulls(): void
    {
        $service = new class {
            #[Autowired]
            public array $items;
        };

        $iterator = new \ArrayIterator(['a' => null, 'b' => 1]);
        $this->injector->inject($service, [
            'items' => $iterator,
        ]);

        // With no default, Traversable is converted as-is and nulls are preserved
        $this->assertArrayHasKey('a', $service->items);
        $this->assertNull($service->items['a']);
        $this->assertEquals(1, $service->items['b']);
    }

    public function testPropertyInjectionWithArrayMergesAndRemovesNullKeys(): void
    {
        $service = new class {
            #[Autowired]
            public array $config = [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => 'value3',
            ];
        };

        $this->injector->inject($service, [
            'config' => [
                'key1' => null,
                'key2' => 'new_value2',
                'key4' => 'value4',
            ],
        ]);

        $config = $service->config;

        $this->assertArrayNotHasKey('key1', $config, 'Null value should remove key from merged array');
        $this->assertEquals('new_value2', $config['key2'], 'Config should override default value');
        $this->assertEquals('value3', $config['key3'], 'Default value should be preserved when not overridden');
        $this->assertEquals('value4', $config['key4'], 'New keys should be added to merged array');
    }

    public function testPropertyInjectionWithArrayNullForNewKeyIsFiltered(): void
    {
        $service = new class {
            #[Autowired]
            public array $config = [
                'key1' => 'value1',
            ];
        };

        $this->injector->inject($service, [
            'config' => [
                'key2' => null,
            ],
        ]);

        $config = $service->config;

        // With non-empty default, null values remove keys from default but are filtered from final result
        // For empty defaults, null values are preserved (e.g., for CollectorDiscovery to disable entries)
        $this->assertArrayNotHasKey('key2', $config, 'New key set to null is filtered out when default is non-empty');
        $this->assertEquals('value1', $config['key1']);
    }

    public function testPropertyInjectionWithArrayOverridesEmptyDefault(): void
    {
        $service = new class {
            #[Autowired]
            public array $items = [];
        };

        $this->injector->inject($service, [
            'items' => ['key1' => 'value1', 'key2' => 'value2'],
        ]);

        $items = $service->items;

        $this->assertArrayHasKey('key1', $items, 'Empty default should be overridden with config values');
        $this->assertArrayHasKey('key2', $items, 'Config values should be preserved');
        $this->assertEquals('value1', $items['key1']);
        $this->assertEquals('value2', $items['key2']);
    }

    public function testPropertyInjectionWithNamedService(): void
    {
        $defaultService = new TestService();
        $customService = new TestService();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => $defaultService,
            'custom' => $customService,
        ]));

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        $this->injector->inject($service, [
            'service' => '#custom',
        ]);

        $this->assertSame($customService, $service->service);
    }

    public function testPropertyInjectionWithPropertyNameAlias(): void
    {
        $namedService = new TestService();
        // Set named service using property name as alias
        $this->container->set(TestServiceInterface::class . '#service', $namedService);

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        $this->injector->inject($service);

        // Should use Type#propertyName alias
        $this->assertSame($namedService, $service->service);
    }

    public function testLazyTypeThrowsExceptionWithMultipleTypes(): void
    {
        $serviceInstance = new \Switon\Di\Tests\Fixtures\TestServiceWithMultipleInterfacesAndLazy();

        // Multiple interfaces should throw ServiceInjectionException
        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);

        $this->injector->inject($serviceInstance);
    }

    public function testLazyTypeWorksWhenLazyNotLast(): void
    {
        $service = new class {
            #[Autowired]
            public \Switon\Core\Lazy|TestServiceInterface $service;
        };

        $this->injector->inject($service);

        // Lazy can appear anywhere in union type, should work correctly
        $this->assertInstanceOf(\Switon\Core\Lazy::class, $service->service);
    }

    public function testPropertyInjectionWithRelativeReference(): void
    {
        $defaultService = new TestService();
        $customService = new TestService();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => $defaultService,
            'custom' => $customService,
        ]));

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        $this->injector->inject($service, [
            'service' => '#custom',
        ]);

        $this->assertSame($customService, $service->service);
    }

    public function testPropertyInjectionWithAbsoluteReference(): void
    {
        $namedService = new TestService();
        $this->container->set(TestServiceInterface::class . '#custom', $namedService);

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        $this->injector->inject($service, [
            'service' => TestServiceInterface::class . '#custom',
        ]);

        $this->assertSame($namedService, $service->service);
    }

    public function testPropertyInjectionWithDirectObjectValue(): void
    {
        $directService = new TestService();

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        $this->injector->inject($service, [
            'service' => $directService,
        ]);

        $this->assertSame($directService, $service->service);
    }


    public function testPropertyInjectionWithScalarUsesDefaultValue(): void
    {
        $service = new class {
            #[Autowired]
            public string $name = 'DefaultName';
        };

        // No value provided, should use default
        $this->injector->inject($service);

        $this->assertEquals('DefaultName', $service->name);
    }

    public function testPropertyInjectionWithScalarParameterOverridesDefault(): void
    {
        $service = new class {
            #[Autowired]
            public string $name = 'DefaultName';
        };

        // Parameter should override default
        $this->injector->inject($service, ['name' => 'CustomName']);

        $this->assertEquals('CustomName', $service->name);
    }

    public function testPrivatePropertyInjectionWithObjectType(): void
    {
        // Arrange
        $this->container->set(TestDependency::class, TestDependency::class);

        $service = new class {
            #[Autowired]
            private TestDependency $dependency;

            public function getDependency(): TestDependency
            {
                return $this->dependency;
            }
        };

        // Act
        $this->injector->inject($service);

        // Assert
        $this->assertInstanceOf(
            TestDependency::class,
            $service->getDependency(),
            'Private object type property should be injected correctly'
        );
    }

    public function testPrivatePropertyInjectionWithScalarAndDefault(): void
    {
        $service = new class {
            #[Autowired]
            private string $name = 'PrivateDefault';

            public function getName(): string
            {
                return $this->name;
            }
        };

        // Act
        $this->injector->inject($service);

        // Assert – default value should be preserved for private scalar properties
        $this->assertSame('PrivateDefault', $service->getName());
    }


    // ============================================================================
    // Inheritance Tests
    // ============================================================================

    public function testPropertyInjectionWithInheritance(): void
    {
        // Arrange
        $this->container->set(TestDependency::class, TestDependency::class);

        $service = new \Switon\Di\Tests\Fixtures\ChildServiceWithProperties();

        // Act
        $this->injector->inject($service);

        // Assert - Base class properties should be injected
        $this->assertInstanceOf(TestDependency::class, $service->baseDependency,
            'Base class dependency should be injected');
        $this->assertSame('BaseDefault', $service->baseName,
            'Base class scalar property should use default value');
        $this->assertSame(['base' => 'value'], $service->baseConfig,
            'Base class array property should use default value');

        // Assert - Child class properties should be injected
        $this->assertInstanceOf(TestService::class, $service->childService,
            'Child class dependency should be injected');
        $this->assertSame('ChildDefault', $service->childName,
            'Child class scalar property should use default value');
        $this->assertSame(['child' => 'value'], $service->childConfig,
            'Child class array property should use default value');
    }

    public function testPropertyInjectionWithDeepInheritance(): void
    {
        // Arrange
        $this->container->set(TestDependency::class, TestDependency::class);
        $this->container->set(TestService::class, TestService::class);

        $service = new \Switon\Di\Tests\Fixtures\GrandChildServiceWithProperties();

        // Act
        $this->injector->inject($service);

        // Assert - All levels should be injected
        $this->assertInstanceOf(TestDependency::class, $service->baseDependency,
            'Base class properties should be injected');
        $this->assertInstanceOf(TestService::class, $service->childService,
            'Child class properties should be injected');
        $this->assertInstanceOf(TestService::class, $service->grandChildService,
            'Grand child class properties should be injected');

        $this->assertSame('BaseDefault', $service->baseName);
        $this->assertSame('ChildDefault', $service->childName);
        $this->assertSame('GrandChildDefault', $service->grandChildName);
    }

    public function testPropertyInjectionWithInheritanceParameterOverride(): void
    {
        // Arrange
        $this->container->set(TestDependency::class, TestDependency::class);

        $service = new \Switon\Di\Tests\Fixtures\ChildServiceWithProperties();

        // Act - Override both base and child properties
        $this->injector->inject($service, [
            'baseName' => 'OverriddenBase',
            'childName' => 'OverriddenChild',
            'baseConfig' => ['base' => 'overridden'],
            'childConfig' => ['child' => 'overridden'],
        ]);

        // Assert
        $this->assertSame('OverriddenBase', $service->baseName,
            'Base class property should be overridden by parameter');
        $this->assertSame('OverriddenChild', $service->childName,
            'Child class property should be overridden by parameter');
        $this->assertSame(['base' => 'overridden'], $service->baseConfig,
            'Base class array should be overridden by parameter');
        $this->assertSame(['child' => 'overridden'], $service->childConfig,
            'Child class array should be overridden by parameter');
    }

    public function testPropertyInjectionWithVeryDeepInheritance(): void
    {
        // Arrange - Test 5-level inheritance chain
        $service = new \Switon\Di\Tests\Fixtures\Level5Service();

        // Act
        $this->injector->inject($service);

        // Assert - All levels should be injected
        $this->assertSame('level1', $service->level1Prop, 'Level 1 property should be injected');
        $this->assertSame('level2', $service->level2Prop, 'Level 2 property should be injected');
        $this->assertSame('level3', $service->level3Prop, 'Level 3 property should be injected');
        $this->assertSame('level4', $service->level4Prop, 'Level 4 property should be injected');
        $this->assertSame('level5', $service->level5Prop, 'Level 5 property should be injected');
    }

    public function testPropertyInjectionWithInheritanceAndPropertyOverride(): void
    {
        // Arrange
        $this->container->set(TestDependency::class, TestDependency::class);

        $service = new \Switon\Di\Tests\Fixtures\ChildServiceWithOverride();

        // Act
        $this->injector->inject($service);

        // Assert - Child class should override parent property
        $this->assertSame('ChildDefault', $service->name,
            'Child class should override parent property with same name');
        $this->assertInstanceOf(TestDependency::class, $service->dependency,
            'Parent class dependency should still be injected');
        $this->assertInstanceOf(TestService::class, $service->childDependency,
            'Child class dependency should be injected');
    }

    public function testPropertyInjectionWithInheritanceArrayMerging(): void
    {
        // Arrange - Don't register TestService directly to avoid interface autowiring exception
        $service = new \Switon\Di\Tests\Fixtures\ChildServiceWithProperties();

        // Act - Test array merging with inheritance (only test array properties, not object dependencies)
        $this->injector->inject($service, [
            'baseConfig' => [
                'base' => 'modified',
                'new' => 'added',
            ],
            'childConfig' => [
                'child' => 'modified',
                'another' => 'added',
            ],
        ]);

        // Assert
        $this->assertSame(['base' => 'modified', 'new' => 'added'], $service->baseConfig,
            'Base class array should be merged correctly');
        $this->assertSame(['child' => 'modified', 'another' => 'added'], $service->childConfig,
            'Child class array should be merged correctly');
    }

    // ============================================================================
    // Type Name Configuration Tests
    // ============================================================================

    public function testPropertyInjectionWithTypeNameStringReference(): void
    {
        // Arrange - Setup factory with multiple instances
        $defaultService = new TestService();
        $customService = new TestService();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => $defaultService,
            'custom' => $customService,
        ]));

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        // Act - Configure by type name with string reference
        $this->injector->inject($service, [
            TestServiceInterface::class => '#custom',
        ]);

        // Assert - Should resolve to 'custom' instance
        $this->assertSame($customService, $service->service,
            'Type name configuration with string reference should work');
    }

    public function testPropertyInjectionWithTypeNameObjectInstance(): void
    {
        // Arrange
        $directService = new TestService();

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        // Act - Configure by type name with object instance
        $this->injector->inject($service, [
            TestServiceInterface::class => $directService,
        ]);

        // Assert - Should use the provided object instance
        $this->assertSame($directService, $service->service,
            'Type name configuration with object instance should work');
    }

    public function testPropertyInjectionPropertyNameTakesPrecedenceOverTypeName(): void
    {
        // Arrange
        $service1 = new TestService();
        $service2 = new TestService();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'service1' => $service1,
            'service2' => $service2,
        ]));

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        // Act - Configure both property name and type name
        $this->injector->inject($service, [
            'service' => '#service1',  // Property name
            TestServiceInterface::class => '#service2',  // Type name
        ]);

        // Assert - Property name should take precedence
        $this->assertSame($service1, $service->service,
            'Property name configuration should take precedence over type name');
    }

    public function testPropertyInjectionWithTypeNameForMultipleProperties(): void
    {
        // Arrange
        $sharedService = new TestService();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'shared' => $sharedService,
        ]));

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service1;

            #[Autowired]
            public TestServiceInterface $service2;
        };

        // Act - Configure by type name (applies to all properties of that type)
        $this->injector->inject($service, [
            TestServiceInterface::class => '#shared',
        ]);

        // Assert - Both properties should get the same instance
        $this->assertSame($sharedService, $service->service1,
            'First property should use type name configuration');
        $this->assertSame($sharedService, $service->service2,
            'Second property should use type name configuration');
        $this->assertSame($service->service1, $service->service2,
            'Both properties should reference the same instance');
    }

    public function testPropertyInjectionWithTypeNameMixedWithPropertyName(): void
    {
        // Arrange
        $defaultService = new TestService();
        $customService = new TestService();
        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'default' => $defaultService,
            'custom' => $customService,
        ]));

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service1;

            #[Autowired]
            public TestServiceInterface $service2;
        };

        // Act - Configure service1 by property name, service2 uses type name
        $this->injector->inject($service, [
            'service1' => '#custom',  // Property name - specific override
            TestServiceInterface::class => '#default',  // Type name - applies to others
        ]);

        // Assert
        $this->assertSame($customService, $service->service1,
            'service1 should use property name configuration');
        $this->assertSame($defaultService, $service->service2,
            'service2 should use type name configuration');
    }

    public function testPropertyInjectionWithTypeNameAbsoluteReference(): void
    {
        // Arrange
        $namedService = new TestService();
        $this->container->set(TestServiceInterface::class . '#custom', $namedService);

        $service = new class {
            #[Autowired]
            public TestServiceInterface $service;
        };

        // Act - Configure by type name with absolute reference
        $this->injector->inject($service, [
            TestServiceInterface::class => TestServiceInterface::class . '#custom',
        ]);

        // Assert
        $this->assertSame($namedService, $service->service,
            'Type name configuration with absolute reference should work');
    }

    public function testIntersectionAutowiredPropertyThrows(): void
    {
        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);
        $this->expectExceptionMessage('intersection type');

        $this->injector->inject(new DiCoverageIntersectionHost());
    }

    public function testUnionOfTwoInterfacesWithoutLazyThrowsAmbiguous(): void
    {
        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);
        $this->expectExceptionMessage('multiple interfaces');

        $this->injector->inject(new DiCoverageAmbiguousInterfacesHost());
    }

    public function testUnionOfTwoConcreteClassesThrowsAmbiguous(): void
    {
        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);
        $this->expectExceptionMessage('ambiguous types');

        $this->injector->inject(new DiCoverageAmbiguousConcretesHost());
    }

    public function testConcreteClassWhenSiblingInterfaceExistsThrowsDipHint(): void
    {
        $this->expectException(\Switon\Di\Exception\InterfaceAutowiringException::class);

        $this->injector->inject(new DiCoverageConcreteEmailHost());
    }

    public function testFactoryObjectInjectedEventWhenReferenceUsesHash(): void
    {
        $dispatcher = $this->createEventDispatcherStub();
        $this->container->set(EventDispatcherInterface::class, $dispatcher);
        $this->container->get(EventDispatcherInterface::class);

        $this->container->set(TestServiceInterface::class, new \Switon\Di\Factory([
            'custom' => TestService::class,
        ]));

        $service = new class {
            #[Autowired]
            public TestServiceInterface $svc;
        };

        $this->injector->inject($service, ['svc' => '#custom']);

        $types = array_map(static fn(object $e) => $e::class, $dispatcher->dispatchedEvents);
        $this->assertContains(FactoryObjectInjected::class, $types);
        foreach ($dispatcher->dispatchedEvents as $event) {
            if ($event instanceof FactoryObjectInjected) {
                $this->assertSame(TestServiceInterface::class, $event->type);
                $this->assertSame('svc', $event->name);
                $this->assertStringContainsString('#', $event->id);

                return;
            }
        }

        $this->fail('Expected FactoryObjectInjected event');
    }

    /** Inline array values for object-typed #[Autowired] properties are resolved via the container make() path. */
    public function testAutowiredObjectPropertyResolvesInlineArrayDefinition(): void
    {
        $this->container->set(TestDependency::class, TestDependency::class);

        $service = new class {
            #[Autowired]
            public TestDependency $dependency;
        };

        $this->injector->inject($service, [
            'dependency' => ['class' => TestDependency::class],
        ]);

        $this->assertInstanceOf(TestDependency::class, $service->dependency);
    }

    /** Union-typed property: parameters may use the concrete class name as key with a direct instance (disambiguates without resolving). */
    public function testUnionObjectPropertyResolvesViaFullyQualifiedClassNameParameter(): void
    {
        $chosen = new TestSecondService();

        $host = new DiCoverageAmbiguousConcretesHost();
        $this->injector->inject($host, [
            TestSecondService::class => $chosen,
        ]);

        $this->assertSame($chosen, $host->svc);
    }

    /** instances: true inline definition where make() fails wraps NotFoundException as ServiceInjectionException. */
    public function testInjectInstancesInlineMakeNotFoundWrapsServiceInjectionException(): void
    {
        $service = new class {
            #[Autowired(instances: true)]
            protected array $services;
        };

        $this->expectException(\Switon\Di\Exception\ServiceInjectionException::class);

        $this->injector->inject($service, [
            'services' => [
                'slot' => ['class' => 'Switon\\Di\\Tests\\Fixtures\\NonexistentInlineClass'],
            ],
        ]);
    }

    /** Nullable array #[Autowired] without parameters resolves to null. */
    public function testAutowiredNullableArrayPropertyIsNullWithoutParameters(): void
    {
        $host = new class {
            #[Autowired]
            public ?array $opts;
        };

        $this->injector->inject($host);

        $this->assertNull($host->opts);
    }

    /** Non-nullable array #[Autowired] without default or parameters requires explicit config. */
    public function testAutowiredNonNullableArrayWithoutConfigThrowsMissingConfiguration(): void
    {
        $host = new class {
            #[Autowired]
            public array $opts;
        };

        $this->expectException(MissingConfigurationException::class);

        $this->injector->inject($host);
    }

    /** Union includes a builtin arm: non-builtin branch still resolves a single concrete service. */
    public function testAutowiredObjectUnionWithBuiltinArmResolvesConcreteType(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $host = new class {
            #[Autowired]
            public TestService|string $dual;
        };

        $this->injector->inject($host);

        $this->assertInstanceOf(TestService::class, $host->dual);
    }
}
