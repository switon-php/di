<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when code registers an explicit binding that the container can auto-map by convention.
 *
 * Guidance: Remove explicit <code>XxxInterface</code> → <code>Xxx</code> bindings when they already match same-namespace auto-mapping.
 *
 * @see \Switon\Di\Container::set()
 */
class RedundantAutoMappingException extends Exception
{
}
