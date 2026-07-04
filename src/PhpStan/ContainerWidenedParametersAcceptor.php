<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Type\ClassStringType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

class ContainerWidenedParametersAcceptor implements ParametersAcceptor
{
    public function __construct(
        protected ParametersAcceptor $inner,
        protected string             $methodName,
    ) {
    }

    public function getTemplateTypeMap(): TemplateTypeMap
    {
        return $this->inner->getTemplateTypeMap();
    }

    public function getResolvedTemplateTypeMap(): TemplateTypeMap
    {
        return $this->inner->getResolvedTemplateTypeMap();
    }

    public function getParameters(): array
    {
        if ($this->methodName !== 'createInstance') {
            return $this->inner->getParameters();
        }

        $parameters = [];
        foreach ($this->inner->getParameters() as $index => $parameter) {
            $parameters[] = $index === 0
                ? new ContainerWidenedParameterReflection($parameter, new ClassStringType())
                : $parameter;
        }

        return $parameters;
    }

    public function isVariadic(): bool
    {
        return $this->inner->isVariadic();
    }

    public function getReturnType(): Type
    {
        return $this->inner->getReturnType();
    }
}
