<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Switon\Core\PhpStan\ClassNameFromTypeResolver;
use Switon\Di\Container;

/**
 * Infers Container::make() return type from the requested class-string.
 */
class ContainerMakeMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use ClassNameFromTypeResolver;

    public function getClass(): string
    {
        return Container::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'make';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        return $this->resolveMakeReturnTypeFromMethodCall($methodCall, $scope);
    }
}
