<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class ConfigurableService
{
    #[Autowired]
    protected LoggerInterface $logger;

    #[Autowired]
    protected string $apiUrl;

    #[Autowired]
    protected int $timeout;

    #[Autowired]
    protected array $allowedMethods = ['GET', 'POST'];

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
