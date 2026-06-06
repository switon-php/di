<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

interface PaymentServiceInterface
{
    public function processPayment(float $amount, string $method): bool;
}
