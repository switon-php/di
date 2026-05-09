<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Circular Dependencies
// ============================================================================

class CircularServiceA
{
    #[Autowired] public CircularServiceB $serviceB;
}
