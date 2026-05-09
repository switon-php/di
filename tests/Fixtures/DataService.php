<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class DataService
{
    #[Autowired]
    protected DatabaseConnection $readConnection;

    #[Autowired]
    protected DatabaseConnection $writeConnection;

    public function getReadConnection(): DatabaseConnection
    {
        return $this->readConnection;
    }

    public function getWriteConnection(): DatabaseConnection
    {
        return $this->writeConnection;
    }
}
