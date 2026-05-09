<?php
declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when container resolution detects a circular dependency or alias loop.
 *
 * @see \Switon\Di\Container::get()
 */
class CircularDependencyException extends Exception
{
}
