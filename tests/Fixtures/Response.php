<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class Response implements ResponseInterface
{
    protected int $statusCode = 200;

    public function json(array $data): string
    {
        return json_encode($data);
    }

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
