<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Switon\Di\Exception;

/**
 * Use when an autowired property or invoked parameter has no type declaration.
 *
 * @see \Switon\Core\Attribute\Autowired
 * @see \Switon\Di\Injector
 */
class MissingTypeDeclarationException extends Exception
{
}
