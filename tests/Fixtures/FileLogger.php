<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class FileLogger implements LoggerInterface
{
    protected array $logs = [];

    public function info(string $message): void
    {
        $this->logs[] = ['level' => 'info', 'message' => $message];
    }

    public function error(string $message): void
    {
        $this->logs[] = ['level' => 'error', 'message' => $message];
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
