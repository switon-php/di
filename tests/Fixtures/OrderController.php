<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class OrderController
{
    #[Autowired]
    protected OrderService $orderService;

    #[Autowired]
    protected ResponseInterface $response;

    public function createAction(int $userId, float $amount): string
    {
        try {
            $order = $this->orderService->createOrder($userId, $amount);

            return $this->response->json([
                'code' => 0,
                'data' => [
                    'id' => $order->id,
                    'userId' => $order->userId,
                    'amount' => $order->amount,
                    'status' => $order->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return $this->response->json([
                'code' => 400,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
