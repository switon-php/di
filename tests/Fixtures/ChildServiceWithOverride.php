<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class ChildServiceWithOverride extends BaseServiceWithOverride
{
    // Child class re-declares same property (PHP allows this)
    #[Autowired] public string $name = 'ChildDefault';

    #[Autowired] public TestServiceInterface $childDependency;
}
