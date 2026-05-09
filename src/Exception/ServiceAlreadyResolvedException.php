<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when code mutates a definition after its singleton instance was already resolved.
 *
 * @see \Switon\Di\Container
*/
class ServiceAlreadyResolvedException extends Exception
{
}
