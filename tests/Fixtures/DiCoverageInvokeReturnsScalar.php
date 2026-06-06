<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

/** Invokable class whose {@see __invoke()} returns a scalar (misuse coverage for factory resolution). */
final class DiCoverageInvokeReturnsScalar
{
    public function __invoke(): int
    {
        return 0;
    }
}
