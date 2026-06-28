<?php

declare(strict_types=1);

namespace Switon\Di\Event;

use JsonSerializable;
use Switon\Core\ContainerInterface;
use Switon\Core\SenderAwareInterface;

use function get_class;

/**
 * Event dispatched when a singleton instance is created by the container.
 *
 * Log category: <code>switon.di.singleton.created</code>
 *
 * @see \Switon\Di\Container
 */
class SingletonCreated implements JsonSerializable, SenderAwareInterface
{
    public function __construct(
        public ContainerInterface $sender,
        public string $id,
        public object $instance,
        public array $definitions
    )
    {

    }

    public function getSender(): ContainerInterface
    {
        return $this->sender;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'instance' => get_class($this->instance),
        ];
    }
}
