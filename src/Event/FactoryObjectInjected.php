<?php

declare(strict_types=1);

namespace Switon\Di\Event;

use JsonSerializable;
use Switon\Core\InjectorInterface;
use Switon\Core\SenderAwareInterface;

/**
 * Event dispatched when a factory object is injected into a property.
 *
 * Log category: <code>switon.di.factory.object.injected</code>
 *
 * @see \Switon\Di\Injector
 */
class FactoryObjectInjected implements JsonSerializable, SenderAwareInterface
{
    public function __construct(public InjectorInterface $sender, public string $type, public string $name, public string $id)
    {

    }

    public function getSender(): InjectorInterface
    {
        return $this->sender;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'id' => $this->id
        ];
    }
}
