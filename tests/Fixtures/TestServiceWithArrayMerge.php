<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class TestServiceWithArrayMerge
{
    #[Autowired] public array $config = [
        'key1' => 'value1',
        'key2' => 'value2',
    ];
}
