<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class CircularServiceB
{
    #[Autowired] public CircularServiceA $serviceA;
}
