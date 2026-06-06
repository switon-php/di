<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class UserRepository implements UserRepositoryInterface
{
    protected array $users = [];
    protected int $nextId = 1;

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function create(User $user): User
    {
        $user->id = $this->nextId++;
        $this->users[$user->id] = $user;
        return $user;
    }

    public function update(User $user): User
    {
        if (isset($this->users[$user->id])) {
            $this->users[$user->id] = $user;
        }
        return $user;
    }
}
