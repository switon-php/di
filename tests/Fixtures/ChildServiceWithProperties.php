<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class ChildServiceWithProperties extends BaseServiceWithProperties
{
    #[Autowired] public TestServiceInterface $childService;

    #[Autowired] public string $childName = 'ChildDefault';

    #[Autowired] public array $childConfig = ['child' => 'value'];
}
