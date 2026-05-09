<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

/**
 * Services for testing property injection with different types.
 *
 * Contains services that test various property injection scenarios:
 * - Scalar types (string, int)
 * - Array types
 * - Nullable types
 * - Default values
 * - Array merging
 * - Lazy loading
 * - Instance arrays
 */

// ============================================================================
// Scalar Property Injection
// ============================================================================

class TestServiceWithScalar
{
    #[Autowired] public string $name;

    #[Autowired] public int $value;
}
