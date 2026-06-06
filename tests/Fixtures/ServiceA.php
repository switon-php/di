<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Complex Dependency Chains
// ============================================================================

class ServiceA
{
    #[Autowired] public ServiceB $serviceB;

    public string $name = 'ServiceA';
}
