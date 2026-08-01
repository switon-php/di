# `#[Autowired]` {#autowired}

`#[Autowired]` injects typed properties before constructor logic runs.

**Key features:**

- **Interface-first wiring:** same-namespace `XxxInterface` → `Xxx` resolves automatically.
- **Pre-constructor injection:** injected values are available inside `__construct()`.
- **Value forms:** services, scalars, arrays, and `Switon\Core\Lazy`.
- **Array instances:** `instances: true` turns array entries into service objects.
- **Explicit redirects:** `#name` and full service IDs can override a property slot.

---

## Quick Start {#quick-start}

```php
namespace App\Service;

use Switon\Core\Attribute\Autowired;
use Switon\Redis\ClientInterface;

class ProductService
{
    #[Autowired] protected ClientInterface $redis;
    #[Autowired] protected int $ttl = 3600;
}
```

```yaml
# switon.yml
App\Service\ProductService:
  ttl: 7200
```

`#[Autowired]` injects `ClientInterface` and applies the scalar override before construction.

---

## Core Concepts {#core-concepts}

### Injection Timing {#injection-timing}

`#[Autowired]` properties are injected before the constructor runs. Plain `new` does not trigger injection.

### Autowiring Rules {#autowiring-rules}

When `XxxInterface` and `Xxx` live in the same namespace, DI resolves them without extra binding.

Use explicit config when:

- the implementation lives in a different namespace
- the type name differs from the implementation class
- one contract has multiple implementations

### Value Forms {#value-forms}

`#[Autowired]` supports these common forms:

- service objects
- scalars
- arrays
- `Switon\Core\Lazy` for deferred resolution

For arrays, config values merge with the default array, `null` removes a key, and `Traversable` replaces the array.
Use `instances: true` when the array should contain resolved service objects instead of class names.

### Explicit Wiring {#explicit-wiring}

Use `#name` when a property should redirect to `Type#name`.
Use a full service ID when the target must be explicit.

---

## Keep in Mind {#keep-in-mind}

- Building autowired objects with `new` skips injection.
- Missing type declarations cannot be autowired.
- Concrete classes are the wrong default when an interface exists.
- `?Type` does not make object injection optional.
- Use `Type|Lazy` for deferred resolution, not nullable object injection.

---

## Next {#next}

- **To see the component overview:** [DI Package](README.md)
- **To learn named instances:** [Factory](factory.md#factory)
- **To answer common questions:** [FAQ](faq.md#di-faq)
- **To understand the trade-offs:** [Design Rationale](design.md#design-rationale)
- **To continue from the first app tutorial:** [Tutorial](../../../docs/en/tutorial/README.md#tutorial)
