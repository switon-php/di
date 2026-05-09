<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Nullable Types
// ============================================================================

class TestServiceWithNullable
{
    #[Autowired] public ?string $optional = null;
}
