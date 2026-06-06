<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Instance Arrays
// ============================================================================

class TestServiceWithInstances
{
    #[Autowired(instances: true)] public array $services;
}
