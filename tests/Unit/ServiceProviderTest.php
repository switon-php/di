<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Core\ContainerInterface;
use Switon\Core\InjectorInterface;
use Switon\Di\Container;
use Switon\Di\InvokerInterface;
use Switon\Di\Injector;
use Switon\Di\ServiceProvider;
use Switon\Di\Tests\Fixtures\TestService;

#[AllowMockObjectsWithoutExpectations]
class ServiceProviderTest extends TestCase
{
    public function testRegisterCreatesInjectorAndInvokerWhenBothMissing(): void
    {
        $provider = new ServiceProvider();
        $container = $this->createMock(ContainerInterface::class);
        $injector = $this->createMock(InjectorInterface::class);

        $container->method('has')
            ->willReturnCallback(static fn(string $id): bool => false);
        $container->expects($this->once())
            ->method('get')
            ->with(InjectorInterface::class)
            ->willReturn($injector);
        $injector->expects($this->once())
            ->method('inject');

        $setCalls = [];
        $container->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $id, mixed $definition) use (&$setCalls, $container): ContainerInterface {
                $setCalls[] = [$id, $definition];
                return $container;
            });

        $provider->register($container);

        $this->assertSame(InjectorInterface::class, $setCalls[0][0]);
        $this->assertInstanceOf(Injector::class, $setCalls[0][1]);
        $this->assertSame(InvokerInterface::class, $setCalls[1][0]);
        $this->assertInstanceOf(InvokerInterface::class, $setCalls[1][1]);
    }

    public function testRegisterSkipsInjectorCreationWhenInjectorAlreadyExists(): void
    {
        $provider = new ServiceProvider();
        $container = $this->createMock(ContainerInterface::class);
        $injector = $this->createMock(InjectorInterface::class);

        $container->method('has')
            ->willReturnCallback(static function (string $id): bool {
                return $id === InjectorInterface::class;
            });
        $container->expects($this->once())
            ->method('get')
            ->with(InjectorInterface::class)
            ->willReturn($injector);
        $injector->expects($this->once())
            ->method('inject');

        $setCalls = [];
        $container->expects($this->once())
            ->method('set')
            ->willReturnCallback(function (string $id, mixed $definition) use (&$setCalls, $container): ContainerInterface {
                $setCalls[] = [$id, $definition];
                return $container;
            });

        $provider->register($container);

        $this->assertCount(1, $setCalls);
        $this->assertSame(InvokerInterface::class, $setCalls[0][0]);
        $this->assertInstanceOf(InvokerInterface::class, $setCalls[0][1]);
    }

    public function testRegisterSkipsAllWhenInjectorAndInvokerAlreadyExist(): void
    {
        $provider = new ServiceProvider();
        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')
            ->willReturnCallback(static function (string $id): bool {
                return $id === InjectorInterface::class || $id === InvokerInterface::class;
            });

        $container->expects($this->never())->method('get');
        $container->expects($this->never())->method('set');

        $provider->register($container);

        $this->addToAssertionCount(1);
    }

    public function testBootIsNoopAndDoesNotThrow(): void
    {
        $provider = new ServiceProvider();
        $provider->boot();

        $this->addToAssertionCount(1);
    }

    public function testRegisterProvidesUsableInvokerForInvokableObjects(): void
    {
        $container = new Container();
        $provider = new ServiceProvider();

        $provider->register($container);

        $container->set(TestService::class, TestService::class);

        $invoker = $container->get(InvokerInterface::class);
        $callable = new class {
            public function __invoke(TestService $service, string ...$parts): array
            {
                return [$service::class, $parts];
            }
        };

        $result = $invoker->invoke($callable, [1 => 'alpha', 2 => 'beta']);

        $this->assertSame([TestService::class, ['alpha', 'beta']], $result);
    }
}
