<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

// ============================================================================
// Required Properties (No Default)
// ============================================================================

class TestServiceWithRequiredProperty
{
    #[Autowired] public string $required;
}
