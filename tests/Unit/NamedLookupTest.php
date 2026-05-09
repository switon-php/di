<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface};
use Switon\Di\Tests\TestCase;

/**
 * Test cases for NamedLookup class.
 */
class NamedLookupTest extends TestCase
{
    public function testByReturnsNamedService(): void
    {
        $defaultService = new TestService();
        $customService = new TestService();
        $factory = new \Switon\Di\Factory([
            'default' => $defaultService,
            'custom' => $customService,
        ]);
        $this->container->set(TestServiceInterface::class, $factory);

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);

        $this->assertSame($defaultService, $lookup->by(TestServiceInterface::class, 'default'));
        $this->assertSame($customService, $lookup->by(TestServiceInterface::class, 'custom'));
    }

    public function testNamesReturnsAllNamesForType(): void
    {
        $factory = new \Switon\Di\Factory([
            'default' => TestService::class,
            'custom' => TestService::class,
            'shared' => TestService::class,
        ]);
        $this->container->set(TestServiceInterface::class, $factory);

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);
        $names = $lookup->names(TestServiceInterface::class);

        $this->assertIsArray($names);
        $this->assertCount(3, $names, 'names() should return all registered named services');
        $this->assertContains('default', $names);
        $this->assertContains('custom', $names);
        $this->assertContains('shared', $names);
    }

    public function testNamesReturnsEmptyArrayWhenNoNamedServices(): void
    {
        // Ensure type exists but is not a Factory (no named services).
        // Using an instance avoids redundant Interface → Class auto-map registration.
        $this->container->set(TestServiceInterface::class, new TestService());

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);
        $names = $lookup->names(TestServiceInterface::class);

        $this->assertIsArray($names);
        $this->assertEmpty($names);
    }

    public function testByThrowsExceptionWhenTypeNotFound(): void
    {
        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);

        $this->expectException(\Switon\Di\Exception\NotFoundException::class);
        $lookup->by('NonExistentType', 'default');
    }

    public function testByThrowsExceptionWhenNameNotFound(): void
    {
        $factory = new \Switon\Di\Factory([
            'default' => new TestService(),
            'custom' => new TestService(),
        ]);
        $this->container->set(TestServiceInterface::class, $factory);

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);

        $this->expectException(\Switon\Di\Exception\NotFoundException::class);
        $lookup->by(TestServiceInterface::class, 'nonexistent');
    }

    public function testByThrowsExceptionWhenTypeIsNotFactory(): void
    {
        $this->container->set(TestServiceInterface::class, new TestService());

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);

        $this->expectException(\Switon\Di\Exception\NotFoundException::class);
        $lookup->by(TestServiceInterface::class, 'default');
    }

    public function testNamesReturnsEmptyArrayWhenTypeNotFound(): void
    {
        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);
        $names = $lookup->names('NonExistentType');

        $this->assertIsArray($names);
        $this->assertEmpty($names);
    }

    public function testNamesReturnsEmptyArrayWhenTypeIsNotFactory(): void
    {
        $this->container->set(TestServiceInterface::class, new TestService());

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);
        $names = $lookup->names(TestServiceInterface::class);

        $this->assertIsArray($names);
        $this->assertEmpty($names);
    }

    public function testByWithEmptyFactory(): void
    {
        $factory = new \Switon\Di\Factory([]);
        $this->container->set(TestServiceInterface::class, $factory);

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);

        $this->expectException(\Switon\Di\Exception\NotFoundException::class);
        $lookup->by(TestServiceInterface::class, 'default');
    }

    public function testNamesWithEmptyFactory(): void
    {
        $factory = new \Switon\Di\Factory([]);
        $this->container->set(TestServiceInterface::class, $factory);

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);
        $names = $lookup->names(TestServiceInterface::class);

        $this->assertIsArray($names);
        $this->assertEmpty($names);
    }

    public function testByWithNullValueInFactory(): void
    {
        $factory = new \Switon\Di\Factory([
            'default' => null,
        ]);
        $this->container->set(TestServiceInterface::class, $factory);

        $lookup = $this->container->get(\Switon\Di\NamedLookupInterface::class);

        $this->expectException(\Switon\Di\Exception\NotFoundException::class);
        $result = $lookup->by(TestServiceInterface::class, 'default');
    }
}

