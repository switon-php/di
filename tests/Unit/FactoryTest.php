<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Factory;
use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface, TestServiceWithParams};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Factory class.
 */
class FactoryTest extends TestCase
{

    public function testFactoryRegistration(): void
    {
        // Arrange
        $factory = new Factory([
            'default' => ['class' => TestService::class],
            'custom' => ['class' => TestServiceWithParams::class, 'name' => 'Custom'],
        ]);
        $this->container->set(TestServiceInterface::class, $factory);

        // Act
        $default = $this->container->get(TestServiceInterface::class);
        $custom = $this->container->get(TestServiceInterface::class . '#custom');

        // Assert
        $this->assertInstanceOf(TestService::class, $default);
        $this->assertInstanceOf(TestServiceWithParams::class, $custom);
    }

    public function testFactoryWithReference(): void
    {
        // Arrange
        $factory = new Factory([
            'default' => ['class' => TestService::class],
            'shared' => '#default',
        ]);
        $this->container->set(TestServiceInterface::class, $factory);

        // Act
        $default = $this->container->get(TestServiceInterface::class);
        $shared = $this->container->get(TestServiceInterface::class . '#shared');

        // Assert
        $this->assertSame($default, $shared);
    }

    public function testFactoryGetDefinitions(): void
    {
        // Arrange
        $definitions = [
            'default' => ['class' => TestService::class],
            'custom' => ['class' => TestServiceWithParams::class],
        ];
        $factory = new Factory($definitions);

        // Act
        $result = $factory->getDefinitions();

        // Assert
        $this->assertEquals($definitions, $result);
    }

    public function testFactoryGetNames(): void
    {
        // Arrange
        $factory = new Factory([
            'default' => ['class' => TestService::class],
            'custom' => ['class' => TestServiceWithParams::class],
            'shared' => '#default',
        ]);

        // Act
        $names = $factory->getNames();

        // Assert
        $this->assertContains('default', $names);
        $this->assertContains('custom', $names);
        $this->assertContains('shared', $names);
        $this->assertCount(3, $names);
    }

    public function testFactoryJsonSerialize(): void
    {
        // Arrange
        $definitions = [
            'default' => ['class' => TestService::class],
        ];
        $factory = new Factory($definitions);

        // Act
        $serialized = $factory->jsonSerialize();

        // Assert
        $this->assertArrayHasKey('definitions', $serialized);
        $this->assertEquals($definitions, $serialized['definitions']);
    }
}

