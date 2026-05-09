<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class PaymentService implements PaymentServiceInterface
{
    #[Autowired]
    protected LoggerInterface $logger;

    #[Autowired]
    protected string $apiKey;

    public function processPayment(float $amount, string $method): bool
    {
        $this->logger->info("Processing payment: $amount via $method");
        return $amount > 0;
    }
}
