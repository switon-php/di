<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Switon\Core\PhpStan\ClassNameFromTypeResolver;
use Switon\Di\Container;

/**
 * Infers createByDefinition() return type from definition shape (object, class array key, or class string).
 */
class ContainerCreateByDefinitionReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use ClassNameFromTypeResolver;

    public function getClass(): string
    {
        return Container::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'createByDefinition';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $args = $methodCall->getArgs();
        if (!isset($args[0])) {
            return null;
        }

        $definitionType = $scope->getType($args[0]->value);

        if ($definitionType->isObject()->yes()) {
            return $definitionType;
        }

        $classType = $this->resolveClassNameFromDefinitionType($definitionType);
        return $classType ?? null;
    }

    protected function resolveClassNameFromDefinitionType(Type $definitionType): ?Type
    {
        if ($definitionType->isString()->yes()) {
            return $this->resolveClassNameType($definitionType);
        }

        if (!$definitionType->isArray()->yes()) {
            return null;
        }

        $constantArrays = $definitionType->getConstantArrays();
        if ($constantArrays !== []) {
            $classNameType = $constantArrays[0]->getOffsetValueType(new ConstantStringType('class'));
            $resolved = $this->resolveClassNameType($classNameType);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $classOffsetType = $definitionType->getOffsetValueType(new ConstantStringType('class'));

        return $this->resolveClassNameType($classOffsetType);
    }
}
