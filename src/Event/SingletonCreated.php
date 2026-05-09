<?php

declare(strict_types=1);

namespace Switon\Di\Event;

use JsonSerializable;
use function get_class;

/**
 * Event dispatched when a singleton instance is created by the container.
 *
 * Log category: <code>switon.di.singleton.created</code>
 *
 * @see \Switon\Di\Container
*/
class SingletonCreated implements JsonSerializable
{
    public function __construct(public string $id, public object $instance, public array $definitions)
    {

    }

    /** @return array{id: string, instance: string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'instance' => get_class($this->instance),
        ];
    }
}
