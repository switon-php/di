<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Tests\Fixtures\{TestDependency, TestService, TestServiceInterface, TestServiceWithDependency};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container autowiring functionality.
 *
 * Tests automatic property injection, interface resolution, and reference resolution.
 */
class ContainerAutowiringTest extends TestCase
{
    public function testAutowireProperties(): void
    {
        // Arrange
        $this->container->set(TestDependency::class, TestDependency::class);

        $service = new TestServiceWithDependency();

        // Act
        $this->injector->inject($service);

        // Assert
        $this->assertInstanceOf(TestDependency::class, $service->dependency,
            'Property autowiring should inject dependency');
    }

    public function testInterfaceAutoMapping(): void
    {
        // Arrange
        // Act
        $service = $this->container->get(TestServiceInterface::class);

        // Assert
        $this->assertInstanceOf(TestService::class, $service,
            'Interface should resolve to its implementation class');
    }

    public function testReferenceResolution(): void
    {
        // Arrange
        $this->container->set(TestService::class, TestService::class);

        // Act
        $service = $this->container->get(TestServiceInterface::class);

        // Assert
        $this->assertInstanceOf(TestService::class, $service,
            'Interface should resolve to its implementation');
    }

    public function testGetServiceWithInterfaceAutoResolution(): void
    {
        $this->container->set(TestService::class, TestService::class);
        // Don't set interface as its own definition - let auto-resolution work
        // Setting interface as its own definition is invalid and will throw MisuseException

        $service = $this->container->get(TestServiceInterface::class);

        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * Test that get() throws InterfaceAutowiringException when class should use interface.
     *
     * Verifies that when a class implements an interface (following Switon naming convention),
     * get() enforces the interface-first autowiring policy and throws an exception
     * if the class name is used directly instead of the interface name.
     */
    public function testGetServiceThrowsInterfaceAutowiringException(): void
    {
        // Arrange - TestService implements TestServiceInterface
        // The interface exists (loaded before class), so interface-first policy applies

        // Act & Assert
        $this->expectException(\Switon\Di\Exception\InterfaceAutowiringException::class);

        $this->container->get(TestService::class);
    }

    /**
     * Test that get() resolves services through string references.
     *
     * Verifies that services can be defined as string references to other services,
     * allowing for service aliasing and indirection.
     */
    public function testGetServiceWithStringReference(): void
    {
        // Arrange
        $this->container->set(TestService::class, TestService::class);
        $this->container->set(TestServiceInterface::class . '#default', TestService::class);
        $this->container->set(TestServiceInterface::class, TestServiceInterface::class . '#default');

        // Act
        $service = $this->container->get(TestServiceInterface::class);

        // Assert
        $this->assertInstanceOf(TestService::class, $service,
            'Service should resolve through string reference to named service');
    }

    /**
     * Test that get() can retrieve services by custom ID.
     *
     * Verifies that services can be registered and retrieved using custom string IDs,
     * not just class names.
     */
    public function testGetServiceWithCustomId(): void
    {
        // Arrange
        $this->container->set('custom.id', TestService::class);

        // Act
        $service = $this->container->get('custom.id');

        // Assert
        $this->assertInstanceOf(TestService::class, $service,
            'Service should be retrievable by custom ID');
    }

    /**
     * Test that an interface-to-interface chain resolves to one shared singleton instance.
     *
     * Verifies that `InterfaceA -> InterfaceB -> Implementation` does not create a
     * separate instance per interface ID.
     */
    public function testInterfaceToInterfaceChainResolvesToSameSingleton(): void
    {
        // Arrange
        $this->container->set(TestAliasRootInterface::class, TestAliasLeafInterface::class);

        // Act
        $root = $this->container->get(TestAliasRootInterface::class);
        $leaf = $this->container->get(TestAliasLeafInterface::class);
        $rootAgain = $this->container->get(TestAliasRootInterface::class);

        // Assert
        $this->assertInstanceOf(TestAliasLeaf::class, $root);
        $this->assertSame($leaf, $root, 'Interface -> interface chain should resolve to one singleton instance');
        $this->assertSame($root, $rootAgain, 'Repeated get() through the first interface should return the cached singleton');
    }

    /**
     * Test that a three-step interface chain still resolves to one shared singleton instance.
     *
     * Verifies that `InterfaceA -> InterfaceB -> InterfaceC -> Implementation` stays on one
     * singleton path and does not break on alias-depth handling.
     */
    public function testThreeStepInterfaceChainResolvesToSameSingleton(): void
    {
        // Arrange
        $this->container->set(TestAliasLevel1Interface::class, TestAliasLevel2Interface::class);
        $this->container->set(TestAliasLevel2Interface::class, TestAliasLevel3Interface::class);

        // Act
        $level1 = $this->container->get(TestAliasLevel1Interface::class);
        $level2 = $this->container->get(TestAliasLevel2Interface::class);
        $level3 = $this->container->get(TestAliasLevel3Interface::class);
        $level1Again = $this->container->get(TestAliasLevel1Interface::class);

        // Assert
        $this->assertInstanceOf(TestAliasLevel3::class, $level1);
        $this->assertSame($level1, $level2, 'First and second interface IDs should share one singleton instance');
        $this->assertSame($level2, $level3, 'Second and third interface IDs should share one singleton instance');
        $this->assertSame($level1, $level1Again, 'Repeated get() through the first interface should return the cached singleton');
    }
}

interface TestAliasRootInterface
{
}

interface TestAliasLeafInterface
{
}

class TestAliasLeaf implements TestAliasLeafInterface
{
}

interface TestAliasLevel1Interface
{
}

interface TestAliasLevel2Interface
{
}

interface TestAliasLevel3Interface
{
}

class TestAliasLevel3 implements TestAliasLevel3Interface
{
}
