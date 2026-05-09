<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class TestServiceWithNullableObject
{
    #[Autowired] public ?MissingOptionalDependency $service = null;
}
