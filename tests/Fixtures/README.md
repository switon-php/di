# Test Fixtures Documentation

This directory contains PSR-4 test fixtures for DI package tests.

## File Organization

- Top-level `*.php`: one class or interface per file under `Switon\\Di\\Tests\\Fixtures`
- Group related fixtures by naming rather than aggregate include files
- Keep only real special bootstrap files outside the PSR-4 model; normal fixtures should stay single-declaration

## Usage

```php
use Switon\Di\Tests\Fixtures\{TestService, TestServiceInterface};
```

## Design Principles

1. **Single Declaration**: Each fixture file owns one class or interface.
2. **Clear Naming**: File names match fixture type names directly.
3. **Direct Autoloading**: Tests import fixture types through Composer PSR-4 autoload.
4. **Easy Discovery**: Related fixtures stay adjacent by naming and directory placement.

## Fixture Groups

### Basic Services

- `TestServiceInterface` (interface) - Base interface for test services
- `TestService` - Simple service implementing `TestServiceInterface`
- `TestServiceWithMethod` - Service with a test method
- `TestServiceWithParams` - Service with constructor parameters
- `TestDependency` - Simple dependency class

### Dependency Patterns

- `TestServiceWithDependency` → depends on `TestDependency`
- `TestServiceWithInterface` → depends on `TestServiceInterface`
- `CircularServiceA` ↔ `CircularServiceB` (circular dependency)
- `ServiceA` → `ServiceB` → `ServiceC` → `TestService` (complex chain)
- `ServiceWithMultipleDependencies` → depends on `ServiceA`, `TestService`, `TestDependency`

### Property Injection

- `TestServiceWithScalar` - Tests scalar type injection (string, int)
- `TestServiceWithArray` - Tests array type injection
- `TestServiceWithArrayMerge` - Tests array merge behavior
- `TestServiceWithDefault` - Tests default value handling
- `TestServiceWithNullable` - Tests nullable type injection
- `TestServiceWithLazy` → depends on `TestServiceInterface|Lazy`
- `TestServiceWithInstances` - Tests instances array injection
- `TestServiceWithRequiredProperty` - Requires non-optional property injection

### Inheritance

- `BaseServiceWithProperties` → depends on `TestDependency`
- `ChildServiceWithProperties` extends `BaseServiceWithProperties` → depends on `TestServiceInterface`
- `GrandChildServiceWithProperties` extends `ChildServiceWithProperties`
- `Level1Service` → `Level2Service` → `Level3Service` → `Level4Service` → `Level5Service` (5-level chain)
- `BaseServiceWithOverride` → `ChildServiceWithOverride` (property overriding)

### Error Handling

- `TestServiceWithFailingConstructor` - Throws exception in constructor

### Real-World App Shapes

- Domain, repository, service, infrastructure, and controller fixtures are also split into direct type-matching files.
