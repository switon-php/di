<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when autowiring requests a concrete class even though a same-namespace interface exists.
 *
 * Guidance: Declare the interface type and let the container auto-map it to the implementation.
 *
 * @see \Switon\Di\Injector
 * @see \Switon\Di\Container::get()
 */
class InterfaceAutowiringException extends Exception
{
}
