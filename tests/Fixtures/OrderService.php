<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class OrderService
{
    #[Autowired]
    public OrderRepository $orderRepository;

    #[Autowired]
    public UserRepositoryInterface $userRepository;

    #[Autowired]
    public PaymentServiceInterface $paymentService;

    #[Autowired]
    public LoggerInterface $logger;

    public function createOrder(int $userId, float $amount): Order
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new \RuntimeException("User not found: $userId");
        }

        $this->logger->info("Creating order for user: $userId, amount: $amount");

        $order = new Order($userId, $amount);

        if ($this->paymentService->processPayment($amount, 'credit_card')) {
            $order->status = 'paid';
        }

        return $this->orderRepository->create($order);
    }

    public function getUserOrders(int $userId): array
    {
        return $this->orderRepository->findByUserId($userId);
    }
}
