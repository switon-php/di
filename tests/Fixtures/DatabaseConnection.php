<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class DatabaseConnection
{
    #[Autowired]
    protected string $host;

    #[Autowired]
    protected string $database;

    public function __construct(string $host, string $database)
    {
        $this->host = $host;
        $this->database = $database;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }
}
