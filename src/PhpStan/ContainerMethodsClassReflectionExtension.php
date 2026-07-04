<?php

declare(strict_types=1);

namespace Switon\Di\PhpStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use Switon\Di\Container;

/**
 * Refines Container internal method signatures used during service construction.
 */
class ContainerMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    protected const array METHODS = [
        'resolveClassName' => true,
        'createInstance' => true,
    ];

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $classReflection->getName() === Container::class
            && isset(self::METHODS[$methodName])
            && $classReflection->hasNativeMethod($methodName);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new ContainerWidenedMethodReflection($classReflection->getNativeMethod($methodName), $methodName);
    }
}
