<?php

declare(strict_types=1);

namespace Switon\Di;

use Psr\Container\ContainerExceptionInterface;

/**
 * Base exception for DI container errors and PSR-11 container failures.
 *
 * @see \Switon\Core\Exception
 * @see \Switon\Di\Exception\NotFoundException
 */
class Exception extends \Switon\Core\Exception implements ContainerExceptionInterface
{
}
