<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class Level4Service extends Level3Service
{
    #[Autowired] public string $level4Prop = 'level4';
}
