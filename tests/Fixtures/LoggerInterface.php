<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

interface LoggerInterface
{
    public function info(string $message): void;

    public function error(string $message): void;
}
