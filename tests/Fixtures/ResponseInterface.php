<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

interface ResponseInterface
{
    public function json(array $data): string;

    public function status(int $code): self;
}
