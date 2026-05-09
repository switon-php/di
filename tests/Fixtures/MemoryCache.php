<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class MemoryCache implements CacheInterface
{
    protected array $cache = [];

    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->cache[$key] = $value;
        return true;
    }
}
