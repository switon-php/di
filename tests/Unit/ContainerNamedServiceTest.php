<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Exception\NotFoundException;
use Switon\Di\Factory;
use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface, TestServiceWithParams};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container named services functionality.
 *
 * Tests named services (Type#name), Factory integration, and relative references.
 */
class ContainerNamedServiceTest extends TestCase
{
    /**
     * Test that get() can retrieve named services with array configuration.
     *
     * Verifies that named services (Type#name) can be registered with array configuration
     * and retrieved correctly with all configuration values injected.
     */
    public function testGetNamedServiceWithArrayConfig(): void
    {
        // Arrange
        $this->container->set(TestServiceWithParams::class . '#custom', [
            'name' => 'CustomName',
            'value' => 200,
        ]);

        // Act
        $service = $this->container->get(TestServiceWithParams::class . '#custom');

        // Assert
        $this->assertInstanceOf(TestServiceWithParams::class, $service);
        $this->assertSame('CustomName', $service->name, 'Named service name should be injected from config');
        $this->assertSame(200, $service->value, 'Named service value should be injected from config');
    }

    public function testGetNamedServiceWithRelativeReference(): void
    {
        // Arrange
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(TestServiceInterface::class . '#default', TestService::class);
        $this->container->set(TestServiceInterface::class . '#custom', '#default');

        // Act
        $service = $this->container->get(TestServiceInterface::class . '#custom');

        // Assert
        $this->assertInstanceOf(TestService::class, $service);
    }

    public function testGetNamedServiceWithAbsoluteReference(): void
    {
        // Arrange - Test absolute reference (not starting with #)
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(TestServiceInterface::class . '#custom', TestService::class);

        // Act
        $service = $this->container->get(TestServiceInterface::class . '#custom');

        // Assert
        $this->assertInstanceOf(TestService::class, $service);
    }

    public function testGetNamedServiceWithReferenceContainingHash(): void
    {
        // Arrange - Test reference string containing # but not starting with #
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(TestServiceInterface::class . '#target', TestService::class);
        // Reference to another named service (absolute reference)
        $this->container->set(TestServiceInterface::class . '#source', TestServiceInterface::class . '#target');

        // Act
        $service = $this->container->get(TestServiceInterface::class . '#source');

        // Assert
        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test that get() throws NotFoundException for non-existent named service.
     *
     * Verifies that when a named service (Type#name) is requested but not registered,
     * get() throws NotFoundException.
     */
    public function testGetNamedServiceThrowsExceptionWhenNotFound(): void
    {
        // Act & Assert
        $this->expectException(NotFoundException::class);

        $this->container->get(TestServiceInterface::class . '#nonexistent');
    }

    public function testNamedServiceWithFactory(): void
    {
        // Arrange
        $factory = new Factory([
            'readonly' => ['class' => TestService::class],
            'writable' => ['class' => TestServiceWithParams::class, 'name' => 'Writable'],
        ]);

        $this->container->set(TestServiceInterface::class, $factory);

        // Act
        $readonly = $this->container->get(TestServiceInterface::class . '#readonly');
        $writable = $this->container->get(TestServiceInterface::class . '#writable');

        // Assert
        $this->assertInstanceOf(TestService::class, $readonly,
            'Named service #readonly should be created from factory');
        $this->assertInstanceOf(TestServiceWithParams::class, $writable,
            'Named service #writable should be created from factory');
    }
}

