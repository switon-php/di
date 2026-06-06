<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

/**
 * Services for testing property injection with inheritance.
 *
 * Contains services that test inheritance scenarios:
 * - Simple inheritance (parent -> child)
 * - Multi-level inheritance (grandparent -> parent -> child)
 * - Deep inheritance chains (5+ levels)
 * - Property overriding in child classes
 * - Array merging across inheritance hierarchy
 */

// ============================================================================
// Simple Inheritance
// ============================================================================

class BaseServiceWithProperties
{
    #[Autowired] public TestDependency $baseDependency;

    #[Autowired] public string $baseName = 'BaseDefault';

    #[Autowired] public array $baseConfig = ['base' => 'value'];
}
