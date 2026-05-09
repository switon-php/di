<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when reflection fails during DI inspection or instantiation.
 *
 * @see \Switon\Di\Injector
*/
class ReflectionException extends Exception
{
}
