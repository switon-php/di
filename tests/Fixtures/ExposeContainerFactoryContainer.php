<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Di\Container;

class ExposeContainerFactoryContainer extends Container
{
    public function callCreateInstance(string $className, array $parameters = [], ?string $id = null): object
    {
        return $this->createInstance($className, $parameters, $id);
    }

    public function callCreateByDefinition(mixed $definition, ?string $id = null): object
    {
        return $this->createByDefinition($definition, $id);
    }
}
