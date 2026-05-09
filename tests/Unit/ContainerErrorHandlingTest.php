<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container error handling.
 *
 * Tests various error conditions and exception scenarios.
 */
class ContainerErrorHandlingTest extends TestCase
{
    /**
     * Test that get() throws MisuseException when interface is set as its own definition.
     *
     * Verifies that when an interface is registered as its own definition (circular reference),
     * get() throws MisuseException to prevent infinite recursion.
     */
    public function testGetServiceThrowsMisuseExceptionForInterfaceAsOwnDefinition(): void
    {
        // Arrange - use a real interface to trigger the MisuseException
        $this->container->set(TestServiceInterface::class, TestServiceInterface::class);

        // Act & Assert
        $this->expectException(\Switon\Core\Exception\MisuseException::class);

        $this->container->get(TestServiceInterface::class);
    }

    /**
     * Test that get() throws NotFoundException for invalid service reference.
     *
     * Verifies that when a service definition references a non-existent service,
     * get() throws NotFoundException.
     */
    public function testGetServiceThrowsExceptionForInvalidReference(): void
    {
        // Arrange
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(TestServiceInterface::class, TestService::class . '#default');

        // Act & Assert
        $this->expectException(\Switon\Di\Exception\NotFoundException::class);

        $this->container->get(TestServiceInterface::class);
    }

    /**
     * Test that UnsupportedDefinitionException is thrown for invalid definition types.
     */
    public function testGetThrowsUnsupportedDefinitionExceptionForInvalidDefinitionType(): void
    {
        // Arrange - Set a definition with invalid type (not string, array, object, or Factory)
        $this->container->set(TestService::class, 12345); // Integer is not a valid definition type

        // Act & Assert
        $this->expectException(\Switon\Di\Exception\UnsupportedDefinitionException::class);

        $this->container->get(TestService::class);
    }

    /**
     * Test that partial config override (array without 'class') preserves class from provider.
     *
     * When Provider registers array def [class=>X, ...] and user config set() with partial
     * [option=>...] (no class), Container preserves 'class' from existing def.
     */
    public function testPartialArrayOverridePreservesClassFromProvider(): void
    {
        // Arrange - Provider-like: full definition for interface
        $this->container->set(TestServiceInterface::class, [
            'class' => TestService::class,
        ]);

        // Act - User config override: partial array without 'class'
        $this->container->set(TestServiceInterface::class, [
            'someOption' => 'value',
        ]);

        // Assert - class preserved; resolution succeeds
        $service = $this->container->get(TestServiceInterface::class);
        $this->assertInstanceOf(TestService::class, $service);
    }

    public function testGetNamedServiceThrowsNotFoundExceptionWhenMissingNamedDefinition(): void
    {
        $this->container->set(TestServiceInterface::class, [
            'class' => TestService::class,
        ]);

        $this->expectException(\Switon\Di\Exception\NotFoundException::class);
        $this->expectExceptionMessage('Named service');

        $this->container->get(TestServiceInterface::class . '#readonly');
    }
}

