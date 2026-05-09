<?php

declare(strict_types=1);

namespace Switon\Di\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Switon\Di\Exception;

/**
 * Use when the container cannot resolve a requested service ID.
 *
 * @see \Switon\Di\Container
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
