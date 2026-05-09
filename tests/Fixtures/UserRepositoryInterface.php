<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function create(User $user): User;

    public function update(User $user): User;
}
