# DI Package {#di-package}

`switon/di` gives Switon apps property-based dependency injection with interface-first autowiring, named slots, and a clear shared-vs-fresh split.

**Key features:**

- **PSR-friendly defaults:** `Psr\Container\ContainerInterface` works out of the box.
- **Interface-first autowiring:** same-namespace `XxxInterface` → `Xxx` resolves automatically.
- **Property injection:** `#[Autowired]` works for services, scalars, arrays, and `Lazy`.
- **Shared vs fresh:** `get()` returns shared services; `make()` creates fresh objects.
- **Named slots:** `Factory` maps one contract to multiple named instances.
- **Alias redirects:** `#[Autowired]` can target `Type#name` slots with `#name` or a full service ID.
- **Runtime lookup:** `NamedLookupInterface` handles dynamic instance selection when the name comes from data.

---

## Installation {#installation}

**Requirements:** PHP 8.3+, `ext-json`

```bash
composer require switon/di
```

---

## Quick Start {#quick-start}

- Inject typed `#[Autowired]` properties.
- Let the container create the object.

Snippets below use illustrative `App\...` names unless a real `Switon\...` or PSR namespace appears.

```php
namespace App\Service;

use Switon\Core\Attribute\Autowired;

interface CacheInterface {}
class Cache implements CacheInterface {}

class ProductService
{
    #[Autowired] protected CacheInterface $cache;
    #[Autowired] protected int $ttl = 3600;
}
```

```yaml
# switon.yml
App\Service\ProductService:
  ttl: 7200
```

- The container creates `ProductService`.
- It injects `CacheInterface`.
- It applies the scalar override from `switon.yml`.

---

## Core Concepts {#core-concepts}

### Interface-First Autowiring {#interface-first-autowiring}

When `XxxInterface` and `Xxx` live in the same namespace, DI resolves them without extra binding.

Use explicit config when the implementation lives elsewhere, the name differs, or one contract has multiple implementations.

### `#[Autowired]` Values {#autowired-values}

`#[Autowired]` covers four common forms:

- service objects
- scalars
- arrays
- `Switon\Core\Lazy` for deferred resolution

Injection happens before constructor logic.
Plain `new` skips it.

### Shared Versus Fresh {#shared-versus-fresh}

Use `get()` for shared services.
Use `make()` for fresh instances.

- `get()` fits stable capabilities
- `make()` fits commands, input objects, and per-use objects

### Named Slots {#named-slots}

When one contract needs multiple instances, use `Switon\Di\Factory`.
The property name becomes the slot, and config decides which named instance fills it.

Use `NamedLookupInterface` only when runtime data chooses the name.

### Alias Redirects {#alias-redirects}

`#[Autowired]` also accepts explicit IDs and relative aliases.

- `#cache` redirects to `Type#cache`
- a full service ID can bypass the slot name when wiring needs to be explicit

Use aliases at wiring edges, not as a normal business-code pattern.

### Scope {#scope}

DI singletons are process-scoped.
Mutable request or connection state belongs in `Switon\Core\ContextAware` and `Switon\Core\ContextManagerInterface`, not in shared service properties.

### Diagnostics {#diagnostics}

Use these commands when you need to inspect the live container:

```bash
bash bin/console container:inspect --id 'App\Service\ProductService'
bash bin/console container:definitions --filter 'App\Service'
bash bin/console container:instances --filter 'App\Service'
```

---

## Configuration {#configuration}

Most apps need configuration only when convention is not enough:

- a service lives in a different namespace
- a property needs a scalar override
- one contract needs multiple named instances

### Explicit Binding {#explicit-binding}

```yaml
# switon.yml
App\Contracts\StorageInterface:
  class: App\Service\S3Storage
```

### Named Instances {#named-instances}

```yaml
# switon.yml
App\Service\CacheInterface:
  class: Switon\Di\Factory
  default:
    uri: redis://localhost/0
  cache:
    uri: redis://localhost/1
```

Then inject the named slot by property name.

---

## Keep in Mind {#keep-in-mind}

- Building autowired objects with `new` skips injection.
- `?Type` does not make autowired object injection optional; use `Type|Lazy` for deferred resolution.
- Using the container as request-state storage mixes lifecycle scopes.
- DI resolves services, objects, named slots, and callable arguments; use [Invoker Package](../../invoker/docs/README.md) when you need guards or interceptors around the final callable.
- Runtime names usually belong in `NamedLookupInterface`, not hard-coded IDs in business code.

---

## Next {#next}

- **To learn property injection rules:** [Autowired](autowired.md#autowired)
- **To learn named instances:** [Factory](factory.md#factory)
- **To answer common questions:** [FAQ](faq.md#di-faq)
- **To understand the trade-offs:** [Design Rationale](design.md#design-rationale)
- **For bootstrap, config merge, and service providers:** [Kernel](../../kernel/docs/README.md)
- **To continue from the first app tutorial:** [Tutorial](../../../docs/en/tutorial/README.md#tutorial)
