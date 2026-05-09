<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use RuntimeException;
use Switon\Di\Exception\MissingConfigurationException;
use Switon\Di\Exception\MissingTypeDeclarationException;
use Switon\Di\Exception\ServiceInjectionException;
use Switon\Di\InvokerInterface;
use Switon\Di\Tests\Fixtures\TestDependency;
use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\TestCase;

class InvokerTest extends TestCase
{
    protected InvokerInterface $invoker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoker = $this->container->get(InvokerInterface::class);
    }

    public function testInvokeWithArrayCallable(): void
    {
        $service = new class {
            public function handle(string $value): string
            {
                return "handled:$value";
            }
        };

        $result = $this->invoker->invoke([$service, 'handle'], ['value' => 'ok']);

        $this->assertSame('handled:ok', $result);
    }

    public function testInvokeWithStringCallable(): void
    {
        $result = $this->invoker->invoke('strlen', ['string' => 'hello']);

        $this->assertSame(5, $result);
    }

    public function testInvokeWithInvokableObjectAndVariadicPositionalValues(): void
    {
        $callable = new class {
            public function __invoke(string $first, string ...$rest): array
            {
                return [$first, ...$rest];
            }
        };

        $result = $this->invoker->invoke($callable, [0 => 'alpha', 1 => 'beta', 2 => 'gamma']);

        $this->assertSame(['alpha', 'beta', 'gamma'], $result);
    }

    public function testInvokeUsesDefaultValueWhenParameterIsMissing(): void
    {
        $closure = static fn(string $name = 'world'): string => "Hello, $name";

        $result = $this->invoker->invoke($closure);

        $this->assertSame('Hello, world', $result);
    }

    public function testInvokeResolvesObjectTypeFromTypeKeyedParameter(): void
    {
        $dependency = new TestDependency();
        $service = new class {
            public function handle(TestDependency $dependency): string
            {
                return $dependency::class;
            }
        };

        $result = $this->invoker->invoke([$service, 'handle'], [TestDependency::class => $dependency]);

        $this->assertSame(TestDependency::class, $result);
    }

    public function testInvokeResolvesObjectTypeFromStringServiceId(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $service = new class {
            public function handle(TestService $service): TestService
            {
                return $service;
            }
        };

        $result = $this->invoker->invoke([$service, 'handle'], ['service' => TestService::class]);

        $this->assertInstanceOf(TestService::class, $result);
    }

    public function testInvokeThrowsMissingTypeDeclarationExceptionForUntypedRequiredParameter(): void
    {
        $service = new class {
            public function handle($value): void
            {
            }
        };

        $this->expectException(MissingTypeDeclarationException::class);

        $this->invoker->invoke([$service, 'handle']);
    }

    public function testInvokeThrowsMissingConfigurationExceptionForBuiltinRequiredParameter(): void
    {
        $service = new class {
            public function handle(int $count): void
            {
            }
        };

        $this->expectException(MissingConfigurationException::class);

        $this->invoker->invoke([$service, 'handle']);
    }

    public function testInvokeThrowsServiceInjectionExceptionWithInterfaceHintWhenDependencyMissing(): void
    {
        $service = new class {
            public function handle(\UnknownPaymentGatewayInterface $gateway): void
            {
            }
        };

        $this->expectException(ServiceInjectionException::class);
        $this->expectExceptionMessage('Check spelling or register implementation in config');

        $this->invoker->invoke([$service, 'handle']);
    }

    public function testInvokeThrowsServiceInjectionExceptionWithClassHintWhenDependencyMissing(): void
    {
        $service = new class {
            public function handle(\UnknownConcreteGateway $gateway): void
            {
            }
        };

        $this->expectException(ServiceInjectionException::class);
        $this->expectExceptionMessage('Check namespace, spelling, or register in config');

        $this->invoker->invoke([$service, 'handle']);
    }

    public function testInvokePropagatesCallableException(): void
    {
        $service = new class {
            public function explode(): never
            {
                throw new RuntimeException('boom');
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->invoker->invoke([$service, 'explode']);
    }

    public function testInvokeThrowsServiceInjectionExceptionForInvokableObjectCallable(): void
    {
        $callable = new class {
            public function __invoke(\UnknownInvokableDependency $dependency): void
            {
            }
        };

        $this->expectException(ServiceInjectionException::class);
        $this->expectExceptionMessage('__invoke() cannot resolve parameter $dependency');

        $this->invoker->invoke($callable);
    }

    public function testInvokeThrowsServiceInjectionExceptionForClosureCallable(): void
    {
        $callable = static function (\UnknownClosureDependency $dependency): void {
        };

        $this->expectException(ServiceInjectionException::class);
        $this->expectExceptionMessage('{closure}() cannot resolve parameter $dependency');

        $this->invoker->invoke($callable);
    }

    public function testInvokeSupportsTypeKeyedVariadicValues(): void
    {
        $callable = new class {
            /**
             * @return list<string>
             */
            public function __invoke(TestService ...$services): array
            {
                return array_map(static fn(TestService $service): string => $service::class, $services);
            }
        };

        $result = $this->invoker->invoke($callable, [
            TestService::class => [new TestService(), new TestService()],
        ]);

        $this->assertSame([TestService::class, TestService::class], $result);
    }

    public function testInvokeSupportsNameKeyedVariadicValues(): void
    {
        $callable = new class {
            /**
             * @return list<string>
             */
            public function __invoke(string ...$parts): array
            {
                return $parts;
            }
        };

        $result = $this->invoker->invoke($callable, [
            'parts' => ['one', 'two'],
        ]);

        $this->assertSame(['one', 'two'], $result);
    }

    public function testInvokeThrowsMissingConfigurationExceptionForClosureRequiredParameter(): void
    {
        $callable = static function (string $value): void {
        };

        $this->expectException(MissingConfigurationException::class);

        $this->invoker->invoke($callable);
    }
}
