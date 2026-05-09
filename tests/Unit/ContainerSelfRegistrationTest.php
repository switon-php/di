<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Tests\TestCase;

/**
 * Test cases for Container self-registration functionality.
 *
 * Tests that Container automatically registers itself for implemented interfaces.
 */
class ContainerSelfRegistrationTest extends TestCase
{
    public function testContainerRegistersItselfAsService(): void
    {
        // Arrange & Act
        $container = new \Switon\Di\Container([]);

        // Assert
        $this->assertTrue($container->has(\Switon\Di\ContainerInterface::class),
            'Container should register itself as ContainerInterface');
        $this->assertTrue($container->has(\Psr\Container\ContainerInterface::class),
            'Container should register itself as PSR ContainerInterface');
    }

    /**
     * Test that container automatically registers itself for all implemented interfaces.
     */
    public function testContainerAutoRegistersSelfInterfaces(): void
    {
        // Container should be available as ContainerInterface
        $this->assertTrue($this->container->has(\Switon\Di\ContainerInterface::class),
            'Container should be available as ContainerInterface');

        $containerInterface = $this->container->get(\Switon\Di\ContainerInterface::class);
        $this->assertSame($this->container, $containerInterface,
            'Container should return itself when requested as ContainerInterface');
    }


    /**
     * Test that registerSelfInterfaces does not override existing definitions passed to constructor.
     */
    public function testRegisterSelfInterfacesDoesNotOverrideExistingDefinitions(): void
    {
        // Create a custom container to use as ContainerInterface definition
        $customContainer = new \Switon\Di\Container();

        // Create container with ContainerInterface already defined
        $container = new \Switon\Di\Container([
            \Switon\Di\ContainerInterface::class => $customContainer
        ]);

        // Container should return the custom container, not itself
        $result = $container->get(\Switon\Di\ContainerInterface::class);
        $this->assertSame($customContainer, $result,
            'Existing ContainerInterface definition should not be overridden by registerSelfInterfaces');
        $this->assertNotSame($container, $result,
            'Container should not override existing definition passed to constructor');
    }
}

