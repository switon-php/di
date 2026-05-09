<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

/** Two concrete classes in a union — ambiguous non-interface resolution. */
class DiCoverageAmbiguousConcretesHost
{
    #[Autowired]
    public TestService|TestSecondService $svc;
}
