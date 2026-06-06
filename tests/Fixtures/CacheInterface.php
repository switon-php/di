<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 3600): bool;
}
