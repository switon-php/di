<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\Type;

final class ContainerWidenedParameterReflection implements ParameterReflection
{
    public function __construct(
        private ParameterReflection $inner,
        private Type                $type,
    ) {
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function isOptional(): bool
    {
        return $this->inner->isOptional();
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function passedByReference(): PassedByReference
    {
        return $this->inner->passedByReference();
    }

    public function isVariadic(): bool
    {
        return $this->inner->isVariadic();
    }

    public function getDefaultValue(): ?Type
    {
        return $this->inner->getDefaultValue();
    }
}
