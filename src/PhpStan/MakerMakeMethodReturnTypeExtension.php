<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Switon\Core\MakerInterface;
use Switon\Core\PhpStan\ClassNameFromTypeResolver;

/**
 * Infers MakerInterface::make() return type from the requested class-string.
 */
class MakerMakeMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use ClassNameFromTypeResolver;

    public function getClass(): string
    {
        return MakerInterface::class;
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
