<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class Request implements RequestInterface
{
    protected array $body = [];
    protected array $query = [];

    public function __construct(array $body = [], array $query = [])
    {
        $this->body = $body;
        $this->query = $query;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }
}
