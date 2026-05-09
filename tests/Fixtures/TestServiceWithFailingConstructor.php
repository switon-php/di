<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

/**
 * Services for testing error conditions and edge cases.
 *
 * Contains services that intentionally trigger errors:
 * - Constructor failures
 * - Missing required properties
 * - Invalid configurations
 */
class TestServiceWithFailingConstructor
{
    public function __construct()
    {
        throw new \RuntimeException('Constructor failed');
    }
}
