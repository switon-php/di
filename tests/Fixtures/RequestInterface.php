<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

interface RequestInterface
{
    public function getBody(): array;

    public function get(string $key, mixed $default = null): mixed;
}
