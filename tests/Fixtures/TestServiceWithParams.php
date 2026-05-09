<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class TestServiceWithParams implements TestServiceInterface
{
    public string $name;
    public int $value;

    public function __construct(string $name = 'Default', int $value = 0)
    {
        $this->name = $name;
        $this->value = $value;
    }
}
