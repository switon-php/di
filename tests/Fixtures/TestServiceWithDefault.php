<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Default Values
// ============================================================================

class TestServiceWithDefault
{
    #[Autowired] public string $name = 'DefaultName';
}
