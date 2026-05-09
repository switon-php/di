<?php

declare(strict_types=1);

namespace Switon\Di\Command;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Di\ContainerInterface;
use Switon\Di\FactoryInterface;
use function count;
use function get_class;
use function is_array;
use function is_object;
use function is_string;
use function json_encode;
use function ksort;
use function sprintf;
use function str_contains;
use function strlen;

/**
 * Inspect container definitions and resolved instances.
 *
 * @see \Switon\Di\ContainerInterface
 * @see \Switon\Core\ConsoleInterface
 */
class ContainerCommand
{
    #[Autowired] protected ConsoleInterface $console;
    #[Autowired] protected ContainerInterface $container;

    /**
     * Show one service definition and whether it is already instantiated.
     *
     * @param string $id Service ID to inspect.
     */
    public function inspectAction(string $id): int
    {
        return $this->inspect($id);
    }

    /**
     * List explicit service definitions.
     *
     * @param string|null $filter Filter service IDs by substring.
     */
    public function definitionsAction(?string $filter = null): int
    {
        return $this->renderDefinitionsTable($this->filterBySubstring($this->container->getDefinitions(), $filter));
    }

    /**
     * List instantiated singleton services.
     *
     * @param string|null $filter Filter service IDs by substring.
     */
    public function instancesAction(?string $filter = null): int
    {
        return $this->renderInstancesTable($this->filterBySubstring($this->container->getInstances(), $filter));
    }

    /** Filters an associative array by substring match on the key. */
    protected function filterBySubstring(array $items, ?string $filter): array
    {
        ksort($items);
        if ($filter === null || $filter === '') {
            return $items;
        }

        return array_filter($items, static fn($_value, $id) => str_contains((string)$id, $filter), ARRAY_FILTER_USE_BOTH);
    }

    protected function inspect(string $id): int
    {
        $exists = $this->container->has($id);
        if (!$exists) {
            $this->console->writeLn($this->console->colorize('Service not found: ', 1) . $id);
            return 1;
        }

        $this->console->writeLn($this->console->colorize('Service: ', 36) . $id);
        $this->console->writeLn(str_repeat('=', 50));

        $definition = $this->container->getDefinition($id);
        $this->console->writeLn($this->console->colorize('Definition:', 6));
        if ($definition !== null) {
            $this->console->writeLn('  Type: ' . $this->getDefinitionType($definition));
            $this->console->writeLn('  Value: ' . $this->formatDefinition($definition));
        } else {
            $this->console->writeLn('  <auto-resolved>');
        }

        $instances = $this->container->getInstances();
        $this->console->writeLn();
        $this->console->writeLn($this->console->colorize('Instance:', 6));
        if (isset($instances[$id])) {
            $this->console->writeLn('  Instantiated as: ' . get_class($instances[$id]));
        } else {
            $this->console->writeLn('  Not instantiated');
        }

        return 0;
    }

    protected function renderDefinitionsTable(array $definitions): int
    {
        if ($definitions === []) {
            $this->console->writeLn('No matching service definitions.');
            return 0;
        }

        $idWidth = 0;
        $typeWidth = 0;
        foreach ($definitions as $id => $definition) {
            $idWidth = max($idWidth, strlen((string)$id));
            $typeWidth = max($typeWidth, strlen($this->getDefinitionType($definition)));
        }

        $idWidth = max($idWidth, 10);
        $typeWidth = max($typeWidth, 12);

        $this->console->writeLn(
            $this->console->colorize(str_pad('SERVICE ID', $idWidth), 36) .
            '  ' .
            $this->console->colorize(str_pad('TYPE', $typeWidth), 36) .
            '  ' .
            $this->console->colorize('DEFINITION', 36)
        );
        $this->console->writeLn(str_repeat('-', $idWidth + $typeWidth + 20));

        foreach ($definitions as $id => $definition) {
            $type = $this->getDefinitionType($definition);
            $this->console->writeLn(
                str_pad((string)$id, $idWidth) . '  ' .
                $this->console->colorize(str_pad($type, $typeWidth), $this->getTypeColor($type)) . '  ' .
                $this->formatDefinition($definition)
            );
        }

        $this->console->writeLn();
        $this->console->writeLn(sprintf('Total: %d service definition(s)', count($definitions)));

        return 0;
    }

    protected function renderInstancesTable(array $instances): int
    {
        if ($instances === []) {
            $this->console->writeLn('No matching service instances.');
            return 0;
        }

        $idWidth = 0;
        $classWidth = 0;
        foreach ($instances as $id => $instance) {
            $idWidth = max($idWidth, strlen((string)$id));
            $classWidth = max($classWidth, strlen(get_class($instance)));
        }

        $idWidth = max($idWidth, 10);
        $classWidth = max($classWidth, 12);

        $this->console->writeLn(
            $this->console->colorize(str_pad('SERVICE ID', $idWidth), 36) .
            '  ' .
            $this->console->colorize(str_pad('CLASS', $classWidth), 36)
        );
        $this->console->writeLn(str_repeat('-', $idWidth + $classWidth + 10));

        foreach ($instances as $id => $instance) {
            $this->console->writeLn(
                str_pad((string)$id, $idWidth) . '  ' .
                $this->console->colorize(str_pad(get_class($instance), $classWidth), 2)
            );
        }

        $this->console->writeLn();
        $this->console->writeLn(sprintf('Total: %d instantiated service(s)', count($instances)));

        return 0;
    }

    /** Returns a short human-readable type label for one definition. */
    protected function getDefinitionType(mixed $definition): string
    {
        if (is_array($definition)) {
            return 'Config';
        }

        if (is_string($definition)) {
            return str_contains($definition, '#') ? 'Reference' : 'Class';
        }

        if (is_object($definition)) {
            if ($definition instanceof FactoryInterface) {
                return 'Factory';
            }

            if ($definition instanceof \Closure) {
                return 'Closure';
            }

            return get_class($definition);
        }

        return 'Unknown';
    }

    /** Formats one service definition for console output. */
    protected function formatDefinition(mixed $definition): string
    {
        if (is_string($definition)) {
            return $definition;
        }

        if (is_object($definition)) {
            if ($definition instanceof FactoryInterface) {
                return 'Factory: ' . get_class($definition);
            }

            return 'Instance: ' . get_class($definition);
        }

        if (is_array($definition)) {
            if (isset($definition['class'])) {
                return 'Config: ' . $definition['class'];
            }

            return 'Config: ' . json_encode($definition, JSON_UNESCAPED_SLASHES);
        }

        return (string)$definition;
    }

    /** Returns the console color for one definition type. */
    protected function getTypeColor(string $type): int
    {
        return match ($type) {
            'Class' => 2,
            'Config' => 5,
            'Factory' => 6,
            'Reference' => 6,
            'Closure' => 4,
            default => 4,
        };
    }
}
