<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class Level3Service extends Level2Service
{
    #[Autowired] public string $level3Prop = 'level3';
}
