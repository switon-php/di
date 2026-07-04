<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

class ContainerWidenedMethodReflection implements MethodReflection
{
    public function __construct(
        protected MethodReflection $inner,
        protected string           $methodName,
    ) {
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->inner->getDeclaringClass();
    }

    public function isStatic(): bool
    {
        return $this->inner->isStatic();
    }

    public function isPrivate(): bool
    {
        return $this->inner->isPrivate();
    }

    public function isPublic(): bool
    {
        return $this->inner->isPublic();
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this->inner->getPrototype();
    }

    public function getVariants(): array
    {
        $variants = [];
        foreach ($this->inner->getVariants() as $variant) {
            $variants[] = new ContainerWidenedParametersAcceptor($variant, $this->methodName);
        }

        return $variants;
    }

    public function isDeprecated(): TrinaryLogic
    {
        return $this->inner->isDeprecated();
    }

    public function getDeprecatedDescription(): ?string
    {
        return $this->inner->getDeprecatedDescription();
    }

    public function isFinal(): TrinaryLogic
    {
        return $this->inner->isFinal();
    }

    public function isInternal(): TrinaryLogic
    {
        return $this->inner->isInternal();
    }

    public function getThrowType(): ?Type
    {
        return $this->inner->getThrowType();
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return $this->inner->hasSideEffects();
    }

    public function getDocComment(): ?string
    {
        return $this->inner->getDocComment();
    }
}
