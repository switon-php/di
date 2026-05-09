<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

interface EmailServiceInterface
{
    public function send(string $to, string $subject, string $body): bool;
}
