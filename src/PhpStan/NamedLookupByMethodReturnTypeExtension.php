<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Switon\Core\PhpStan\ClassNameFromTypeResolver;
use Switon\Di\NamedLookupInterface;

/**
 * Infers NamedLookupInterface::by() return type from the looked-up class-string.
 */
class NamedLookupByMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use ClassNameFromTypeResolver;

    public function getClass(): string
    {
        return NamedLookupInterface::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'by';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $args = $methodCall->getArgs();
        if (!isset($args[0])) {
            return null;
        }

        return $this->resolveClassNameType($scope->getType($args[0]->value));
    }
}
