<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class OrderRepository
{
    protected array $orders = [];
    protected int $nextId = 1;

    public function create(Order $order): Order
    {
        $order->id = $this->nextId++;
        $this->orders[$order->id] = $order;
        return $order;
    }

    public function findByUserId(int $userId): array
    {
        return array_filter($this->orders, fn($order) => $order->userId === $userId);
    }
}
