<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Deep Inheritance Chain (5 Levels)
// ============================================================================

class Level1Service
{
    #[Autowired] public string $level1Prop = 'level1';
}
