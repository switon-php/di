<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class Level5Service extends Level4Service
{
    #[Autowired] public string $level5Prop = 'level5';
}
