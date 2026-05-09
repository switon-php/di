<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when property injection or callable parameter resolution fails.
 *
 * @see \Switon\Core\Attribute\Autowired
 * @see \Switon\Di\Injector
 * @see \Switon\Di\Injector::resolveDependency()
*/
class ServiceInjectionException extends Exception
{
}
