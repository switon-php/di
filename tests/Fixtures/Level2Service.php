<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class Level2Service extends Level1Service
{
    #[Autowired] public string $level2Prop = 'level2';
}
