<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

class TestServiceWithMethod implements TestServiceInterface
{
    public function testMethod(): string
    {
        return 'test';
    }
}
