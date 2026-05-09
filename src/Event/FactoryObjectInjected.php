<?php
declare(strict_types=1);

namespace Switon\Di\Event;

use JsonSerializable;

/**
 * Event dispatched when a factory object is injected into a property.
 *
 * Log category: <code>switon.di.factory.object.injected</code>
 *
 * @see \Switon\Di\Injector
*/
class FactoryObjectInjected implements JsonSerializable
{
    public function __construct(public string $type, public string $name, public string $id)
    {

    }

    /** @return array{type: string, name: string, id: string} */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'id' => $this->id
        ];
    }
}
