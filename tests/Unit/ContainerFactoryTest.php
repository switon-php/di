<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Factory;
use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface, TestServiceWithParams};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container Factory integration.
 *
 * Tests Factory registration and usage with named services.
 */
class ContainerFactoryTest extends TestCase
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
        $this->assertInstanceOf(TestService::class, $default, 'Default service should be created from factory');
        $this->assertInstanceOf(TestServiceWithParams::class, $custom, 'Custom named service should be created from factory');
    }

    public function testFactoryRegistrationFromArrayDefinition(): void
    {
        // Arrange: Factory configured as an array definition (YAML-compatible)
        $this->container->set(TestServiceInterface::class, [
            'class' => Factory::class,
            'default' => ['class' => TestService::class],
            'custom' => ['class' => TestServiceWithParams::class, 'name' => 'Custom'],
        ]);

        // Act
        $default = $this->container->get(TestServiceInterface::class);
        $custom = $this->container->get(TestServiceInterface::class . '#custom');

        // Assert: container should resolve Type to #default, not to the Factory instance
        $this->assertInstanceOf(TestService::class, $default);
        $this->assertInstanceOf(TestServiceWithParams::class, $custom);
    }
}

