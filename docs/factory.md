# `Factory` {#factory}

`Switon\Di\Factory` maps one contract to multiple named instances. The plain type resolves to `#default`.

**Key features:**

- **Property-name slots:** `$cache` resolves to `Type#cache`.
- **Default fallback:** the plain `Type` resolves to `#default`.
- **Alias slots:** one slot can point at another with `#name`.
- **Runtime lookup:** `NamedLookupInterface` is for names that come from data.
- **Separate bindings:** named slots stay separate from the main `Type` binding.

---

## Quick Start {#quick-start}

```yaml
# switon.yml
Switon\Redis\ClientInterface:
  class: Switon\Di\Factory
  default:
    uri: redis://localhost:6379/0
  cache:
    uri: redis://localhost:6379/1
  session:
    uri: redis://localhost:6379/2
```

```php
use Switon\Core\Attribute\Autowired;
use Switon\Redis\ClientInterface;

class MyService
{
    #[Autowired] protected ClientInterface $redis;
    #[Autowired] protected ClientInterface $cache;
    #[Autowired] protected ClientInterface $session;
}
```

`$redis` uses `default`; `$cache` and `$session` match their slots.

---

## Core Concepts {#core-concepts}

### Slot Matching {#slot-matching}

- The property name is the slot name.
- `Type#propertyName` is the first match.
- `Type` falls back to `#default`.
- Missing `default` fails fast for unmatched names.

### Explicit Slot Redirects {#explicit-slot-redirects}

Use `#name` when the property and slot names differ.

```yaml
App\Service\MyService:
  redis: '#cache'
```

### Runtime Lookup {#runtime-lookup}

- Use `NamedLookupInterface` only when the target name comes from runtime data.
- If the slot is fixed in code, property injection is the simpler path.

---

## Keep in Mind {#keep-in-mind}

- `remove(Type::class)` does not remove `Type#name` entries.
- Unmatched names fail when there is no `default` slot.
- `NamedLookupInterface` is not the normal path for stable slot names.

---

## Next {#next}

- **To see the component overview:** [DI Package](README.md)
- **To learn property injection:** [Autowired](autowired.md#autowired)
- **To answer common questions:** [FAQ](faq.md#di-faq)
- **To understand the trade-offs:** [Design Rationale](design.md#design-rationale)
- **To continue from the first app tutorial:** [Tutorial](../../../docs/en/tutorial/README.md#tutorial)
