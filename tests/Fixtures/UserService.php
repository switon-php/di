<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class UserService
{
    #[Autowired]
    public UserRepositoryInterface $userRepository;

    #[Autowired]
    public EmailServiceInterface $emailService;

    #[Autowired]
    public LoggerInterface $logger;

    #[Autowired]
    public string $appName;

    public function createUser(string $name, string $email): User
    {
        $this->logger->info("Creating user: $name");

        $user = new User($name, $email);
        $user = $this->userRepository->create($user);

        $this->emailService->send(
            $user->email,
            "Welcome to {$this->appName}",
            "Hello {$user->name}, welcome to our application!"
        );

        return $user;
    }

    public function getUser(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }
}
