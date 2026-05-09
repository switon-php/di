<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Di\Container;

/** Exposes protected Container::getById() for coverage (named ID with explicit definition). */
final class ExposeGetByIdContainer extends Container
{
    public function callGetById(string $id): mixed
    {
        return $this->getById($id);
    }
}
