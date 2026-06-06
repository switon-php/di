<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

/**
 * Services with various dependency injection patterns.
 *
 * Contains services that demonstrate different dependency injection scenarios:
 * - Simple dependencies
 * - Interface dependencies
 * - Circular dependencies
 * - Complex dependency chains
 * - Multiple dependencies
 */

// ============================================================================
// Simple Dependencies
// ============================================================================

class TestServiceWithDependency
{
    #[Autowired] public TestDependency $dependency;
}
