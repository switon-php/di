<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

interface DiCoverageIntersectionA
{
}

interface DiCoverageIntersectionB
{
}

/** Intersection-typed autowired property (unsupported by Injector). */
class DiCoverageIntersectionHost
{
    #[Autowired]
    public DiCoverageIntersectionA&DiCoverageIntersectionB $both;
}
