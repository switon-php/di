<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when a container definition format is not supported.
 *
 * @see \Switon\Di\Container
*/
class UnsupportedDefinitionException extends Exception
{
}
