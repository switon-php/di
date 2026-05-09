<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Unit;

use Stringable;
use Switon\Di\Command\ContainerCommand;
use Switon\Di\Container;
use Switon\Di\Factory;
use Switon\Di\ServiceProvider;
use Switon\Di\Tests\Fixtures\TestService;
use Switon\Di\Tests\Fixtures\TestServiceWithParams;
use Switon\Di\Tests\TestCase;

class ContainerCommandTest extends TestCase
{
    protected ContainerCommand $command;
    protected MockConsole $console;

    protected function setUp(): void
    {
        parent::setUp();

        $this->console = new MockConsole();
        $this->container->set(\Switon\Core\ConsoleInterface::class, $this->console);
        $this->command = $this->container->make(ContainerCommand::class);
    }

    protected function assertOutputContainsString(string $needle): void
    {
        $this->assertStringContainsString($needle, implode("\n", $this->console->output));
    }

    public function testDefinitionsActionWithEmptyContainer(): void
    {
        $exitCode = $this->command->definitionsAction();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('SERVICE ID', $this->console->output[0]);
        $this->assertOutputContainsString('Total:');
    }

    public function testDefinitionsActionWithServices(): void
    {
        $this->container->set(TestService::class, TestService::class);
        $this->container->set('custom.service', ['class' => TestServiceWithParams::class, 'param' => 'test']);

        $exitCode = $this->command->definitionsAction();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('SERVICE ID', $this->console->output[0]);
        $this->assertStringContainsString('TYPE', $this->console->output[0]);
        $this->assertStringContainsString('DEFINITION', $this->console->output[0]);
        $this->assertOutputContainsString(TestService::class);
        $this->assertOutputContainsString('custom.service');
        $this->assertOutputContainsString('Total:');
    }

    public function testInstancesActionWithEmptyContainer(): void
    {
        $exitCode = $this->command->instancesAction();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('SERVICE ID', $this->console->output[0]);
        $this->assertOutputContainsString('Total:');
    }

    public function testInstancesActionWithInstances(): void
    {
        $this->container->set(TestService::class, TestService::class);
        $this->container->get(TestService::class);

        $exitCode = $this->command->instancesAction();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('SERVICE ID', $this->console->output[0]);
        $this->assertStringContainsString('CLASS', $this->console->output[0]);
        $this->assertOutputContainsString(TestService::class);
        $this->assertOutputContainsString('Total:');
    }

    public function testInspectActionWithExistingService(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $exitCode = $this->command->inspectAction(TestService::class);

        $this->assertEquals(0, $exitCode);
        $this->assertOutputContainsString('Service: ' . TestService::class);
        $this->assertOutputContainsString('Definition:');
        $this->assertOutputContainsString('Type: Class');
        $this->assertOutputContainsString('Value: ' . TestService::class);
        $this->assertOutputContainsString('Instance:');
        $this->assertOutputContainsString('Not instantiated');
    }

    public function testInspectActionWithNonExistingService(): void
    {
        $exitCode = $this->command->inspectAction('non.existing.service');

        $this->assertEquals(1, $exitCode);
        $this->assertContains('Service not found: non.existing.service', $this->console->output);
    }

    public function testDefinitionTypes(): void
    {
        $this->container->set('class.service', TestService::class);
        $this->container->set('instance.service', new TestService());
        $this->container->set('config.service', ['class' => TestServiceWithParams::class, 'param' => 'test']);
        $this->container->set('closure.service', fn() => new TestService());
        $this->container->set('factory.service', new Factory(['default' => TestService::class]));

        $exitCode = $this->command->definitionsAction();

        $this->assertEquals(0, $exitCode);
        $this->assertOutputContainsString('Class');
        $this->assertOutputContainsString('Config');
        $this->assertOutputContainsString('Closure');
        $this->assertOutputContainsString(TestService::class);
        $this->assertOutputContainsString('Reference');
        $this->assertOutputContainsString('factory.service#');
    }

    public function testDefinitionsActionWithFilterMatchesSubset(): void
    {
        $this->container->set('app.alpha.service', TestService::class);
        $this->container->set('app.beta.service', TestServiceWithParams::class);

        $exitCode = $this->command->definitionsAction('beta');

        $this->assertSame(0, $exitCode);
        $this->assertOutputContainsString('app.beta.service');
        $this->assertStringNotContainsString('app.alpha.service', implode("\n", $this->console->output));
    }

    public function testDefinitionsActionWithFilterMatchesNothing(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $exitCode = $this->command->definitionsAction('zzzz-no-such-id');

        $this->assertSame(0, $exitCode);
        $this->assertOutputContainsString('No matching service definitions.');
    }

    public function testInstancesActionWithFilterMatchesNothing(): void
    {
        $this->container->set(TestService::class, TestService::class);
        $this->container->get(TestService::class);

        $exitCode = $this->command->instancesAction('zzzz-no-such-id');

        $this->assertSame(0, $exitCode);
        $this->assertOutputContainsString('No matching service instances.');
    }

    public function testInspectShowsAutoResolvedWhenNoExplicitDefinition(): void
    {
        $this->assertNull($this->container->getDefinition(TestService::class));

        $exitCode = $this->command->inspectAction(TestService::class);

        $this->assertSame(0, $exitCode);
        $this->assertOutputContainsString('<auto-resolved>');
        $this->assertOutputContainsString('Not instantiated');
    }

    public function testInspectShowsInstantiatedClassWhenResolved(): void
    {
        $this->container->set(TestServiceWithParams::class, TestServiceWithParams::class);
        $this->container->get(TestServiceWithParams::class);

        $exitCode = $this->command->inspectAction(TestServiceWithParams::class);

        $this->assertSame(0, $exitCode);
        $this->assertOutputContainsString('Instantiated as:');
        $this->assertOutputContainsString(TestServiceWithParams::class);
    }

    public function testDefinitionsTableIncludesUnknownDefinitionType(): void
    {
        $this->container->set('opaque.scalar', 42);

        $exitCode = $this->command->definitionsAction();

        $this->assertSame(0, $exitCode);
        $this->assertOutputContainsString('opaque.scalar');
        $this->assertOutputContainsString('Unknown');
    }

    /** Array definitions without a {@code class} key are formatted as JSON for console output. */
    public function testDefinitionsTableFormatsArrayDefinitionWithoutClassKeyAsJson(): void
    {
        $this->container->set('config.raw.keys', ['region' => 'eu', 'ttl' => 60]);

        $exitCode = $this->command->definitionsAction();

        $this->assertSame(0, $exitCode);
        $this->assertOutputContainsString('config.raw.keys');
        $this->assertOutputContainsString('Config');
        $this->assertOutputContainsString('"region":"eu"');
    }

    /**
     * Factory definitions only remain visible before FactoryInterface::register(); seed the container via constructor,
     * then inspect shows Factory vs stored instance formatting.
     */
    public function testInspectFormatsFactoryAndPlainObjectDefinitionValues(): void
    {
        $factory = new Factory(['default' => TestService::class]);
        $plain = new TestService();

        $container = new Container([
            'cmd.factory' => $factory,
            'cmd.instance' => $plain,
        ]);
        $provider = new ServiceProvider();
        $provider->register($container);
        $provider->boot();

        $console = new MockConsole();
        $container->set(\Switon\Core\ConsoleInterface::class, $console);
        $command = $container->make(ContainerCommand::class);

        $this->assertSame(0, $command->inspectAction('cmd.factory'));
        $outputFactory = implode("\n", $console->output);
        $this->assertStringContainsString('Type: Factory', $outputFactory);
        $this->assertStringContainsString('Factory: ' . Factory::class, $outputFactory);

        $console->output = [];

        $this->assertSame(0, $command->inspectAction('cmd.instance'));
        $outputInstance = implode("\n", $console->output);
        $this->assertStringContainsString('Instance: ' . TestService::class, $outputInstance);
        $this->assertStringContainsString('Type: ' . TestService::class, $outputInstance);
    }

    /** Empty filter string must list all definitions/instances (same as null). */
    public function testDefinitionsAndInstancesActionsWithEmptyStringFilterListAll(): void
    {
        $this->container->set('filter.mark', TestService::class);
        $this->container->get('filter.mark');

        $codeDef = $this->command->definitionsAction('');
        $codeInst = $this->command->instancesAction('');

        $this->assertSame(0, $codeDef);
        $this->assertSame(0, $codeInst);
        $this->assertOutputContainsString('filter.mark');
    }

    /** Definitions table labels Factory-bound rows (covers getTypeColor Factory branch). */
    public function testDefinitionsTableIncludesFactoryTypeFromConstructorDefinition(): void
    {
        $container = new Container([
            'coverage.factory.def' => new Factory(['default' => TestService::class]),
        ]);
        $provider = new ServiceProvider();
        $provider->register($container);
        $provider->boot();

        $console = new MockConsole();
        $container->set(\Switon\Core\ConsoleInterface::class, $console);
        $command = $container->make(ContainerCommand::class);

        $this->assertSame(0, $command->definitionsAction());

        $joined = implode("\n", $console->output);
        $this->assertStringContainsString('coverage.factory.def', $joined);
        $this->assertStringContainsString('Factory', $joined);
    }
}

class MockConsole implements \Switon\Core\ConsoleInterface
{
    public array $output = [];

    public function isSupportColor(): bool
    {
        return false;
    }

    public function colorize(string $text, int $options = 0, int $width = 0): string
    {
        return str_pad($text, $width);
    }

    public function sampleColorizer(): void
    {
    }

    public function write(string|Stringable $message, array $context = [], int $options = 0): void
    {
        $this->output[] = (string)$message;
    }

    public function writeLn(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->output[] = (string)$message;
    }

    public function debug(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->writeLn($message, $context, $options);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    public function table(array $headers, array $rows, int $minWidth = 8, bool $withRowNumber = true): void
    {
    }

    public function success(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    public function error(string|Stringable $message, array $context = [], int $code = 1): int
    {
        $this->writeLn($message, $context);
        return $code;
    }

    public function progress(string|Stringable $message, mixed $value = null): void
    {
        $this->writeLn($message);
    }

    public function read(): string
    {
        return '';
    }

    public function ask(string $message): string
    {
        return '';
    }

    public function confirm(string $message, bool $default = true): bool
    {
        return $default;
    }

    public function choice(string $message, array $options, string|int|null $default = null): string|int
    {
        return $default ?? array_key_first($options);
    }

    public function secret(string $message): string
    {
        return '';
    }

    public function block(string|array $messages, ?string $type = null, ?string $prefix = null, bool $padding = true): void
    {
        $this->writeLn(is_array($messages) ? implode("\n", $messages) : $messages);
    }

    public function section(string $message): void
    {
        $this->writeLn($message);
    }

    public function note(string $message): void
    {
        $this->writeLn($message);
    }

    public function caution(string $message): void
    {
        $this->writeLn($message);
    }

    public function listing(array $items): void
    {
        foreach ($items as $item) {
            $this->writeLn('• ' . $item);
        }
    }

    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->writeLn();
        }
    }

    public function line(string $message = ''): void
    {
        $this->writeLn($message);
    }
}
