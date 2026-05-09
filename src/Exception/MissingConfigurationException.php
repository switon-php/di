<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when a required autowired config value is missing.
 *
 * @see \Switon\Core\Attribute\Autowired
 * @see \Switon\Di\Injector
*/
class MissingConfigurationException extends Exception
{
}
