<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Di\Tests\TestCase;

/**
 * Test cases for complex array merging scenarios in property injection.
 *
 * Tests basic array merging patterns that are actually supported.
 */
class ComplexArrayMergingTest extends TestCase
{
    public function testBasicArrayMerging(): void
    {
        $service = new class {
            #[Autowired]
            public array $config = [
                'default_key' => 'default_value',
                'override_me' => 'original',
            ];
        };

        $overrides = [
            'config' => [
                'override_me' => 'overridden',
                'new_key' => 'new_value',
            ],
        ];

        $this->injector->inject($service, $overrides);

        // Basic merging: config overrides default, new keys are added
        $this->assertArrayHasKey('default_key', $service->config);
        $this->assertArrayHasKey('override_me', $service->config);
        $this->assertArrayHasKey('new_key', $service->config);
        $this->assertEquals('overridden', $service->config['override_me']);
        $this->assertEquals('new_value', $service->config['new_key']);
    }

    public function testTraversableArrayHandling(): void
    {
        $service = new class {
            #[Autowired]
            public array $items = ['default1', 'default2'];
        };

        // Create Traversable data
        $traversableData = new \ArrayIterator(['item1', 'item2', 'item3']);

        $this->injector->inject($service, [
            'items' => $traversableData,
        ]);

        // Traversable should be converted to array
        $this->assertIsArray($service->items);
        $this->assertEquals(['item1', 'item2', 'item3'], $service->items);
    }

    public function testArrayWithNullValues(): void
    {
        $service = new class {
            #[Autowired]
            public array $config = [
                'keep_me' => 'value1',
                'remove_me' => 'value2',
            ];
        };

        $overrides = [
            'config' => [
                'remove_me' => null,
                'add_me' => 'new_value',
            ],
        ];

        $this->injector->inject($service, $overrides);

        // Verify basic merging behavior
        $this->assertArrayHasKey('keep_me', $service->config);
        $this->assertArrayHasKey('add_me', $service->config);
        $this->assertEquals('new_value', $service->config['add_me']);
    }

    public function testEmptyArrayDefault(): void
    {
        $service = new class {
            #[Autowired]
            public array $items = [];
        };

        $overrides = [
            'items' => ['item1', 'item2'],
        ];

        $this->injector->inject($service, $overrides);

        $this->assertEquals(['item1', 'item2'], $service->items);
    }

    public function testArrayMergingWithModerateSizedArray(): void
    {
        $service = new class {
            #[Autowired]
            public array $items = [];
        };

        $items = [];
        for ($i = 0; $i < 100; $i++) {
            $items["item_$i"] = "value_$i";
        }

        $this->injector->inject($service, [
            'items' => $items,
        ]);

        $this->assertCount(100, $service->items);
        $this->assertSame('value_0', $service->items['item_0']);
        $this->assertSame('value_99', $service->items['item_99']);
    }

    public function testNullableArrayProperty(): void
    {
        $service = new class {
            #[Autowired]
            public ?array $optionalConfig = null;
        };

        $overrides = [
            'optionalConfig' => ['key' => 'value'],
        ];

        $this->injector->inject($service, $overrides);

        $this->assertEquals(['key' => 'value'], $service->optionalConfig);
    }

    /**
     * When every default key is explicitly nulled, merge falls back to filtered config (empty default branch in mergeWithDefault).
     */
    public function testMergeWhenAllDefaultKeysExplicitlyNulled(): void
    {
        $service = new class {
            #[Autowired]
            public array $config = [
                'drop_a' => 'a',
                'drop_b' => 'b',
            ];
        };

        $this->injector->inject($service, [
            'config' => [
                'drop_a' => null,
                'drop_b' => null,
                'only_config' => 'kept',
            ],
        ]);

        $this->assertSame(['only_config' => 'kept'], $service->config);
    }
}