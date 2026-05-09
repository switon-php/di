<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Multi-Level Inheritance
// ============================================================================

class GrandChildServiceWithProperties extends ChildServiceWithProperties
{
    #[Autowired] public TestServiceInterface $grandChildService;

    #[Autowired] public string $grandChildName = 'GrandChildDefault';
}
