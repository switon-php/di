<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Property Overriding
// ============================================================================

class BaseServiceWithOverride
{
    #[Autowired] public string $name = 'BaseDefault';

    #[Autowired] public TestDependency $dependency;
}
