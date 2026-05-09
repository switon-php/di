<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Lazy Loading
// ============================================================================

class TestServiceWithLazy
{
    #[Autowired] public TestServiceInterface|\Switon\Core\Lazy $service;
}
