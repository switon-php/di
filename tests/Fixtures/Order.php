<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class Order
{
    public int $id;
    public int $userId;
    public float $amount;
    public string $status = 'pending';

    public function __construct(int $userId, float $amount)
    {
        $this->userId = $userId;
        $this->amount = $amount;
    }
}
