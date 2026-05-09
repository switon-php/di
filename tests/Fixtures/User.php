<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class User
{
    public int $id;
    public string $name;
    public string $email;
    public bool $active = true;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }
}
