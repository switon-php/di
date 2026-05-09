<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Multiple Dependencies
// ============================================================================

class ServiceWithMultipleDependencies
{
    #[Autowired] public ServiceA $serviceA;

    #[Autowired] public TestServiceInterface $testService;

    #[Autowired] public TestDependency $testDependency;
}
