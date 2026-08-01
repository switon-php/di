# Design Rationale {#design-rationale}

DI is the layer that keeps Switon application code organized around dependencies instead of around the container.

## See also {#see-also}

- [DI Package](README.md) — wiring, autowiring, and container boundaries
- [FAQ](faq.md#di-faq) — short answers for common wiring problems
- [Autowired](autowired.md#autowired) — property injection rules in detail
- [Factory](factory.md#factory) — named instances and slot selection
- [Kernel Package](../../kernel/docs/README.md) — env, config merge, and provider bootstrap ordering
- [Context Package](../../context/docs/README.md) — request-scoped state instead of container singletons

---

## Shared Dependency Pattern {#shared-dependency-shape}

Most application drift starts when each module invents its own dependency style. DI exists to keep Switon code consistent across apps: typed properties, contract-first resolution, and a clear split between shared services and fresh objects.

## Interface First {#interface-first}

Switon treats same-namespace `XxxInterface` → `Xxx` as the normal path. That keeps the common case registration-free and pushes explicit bindings only to the cases that really need them.

## Property Injection First {#property-injection-first}

`#[Autowired]` keeps dependency declarations close to the class that uses them and runs before constructor logic. That makes the normal path short, but it also means plain `new` is not the injection path.

## Named Slots Instead of Named IDs in Business Code {#named-slots}

When one contract needs multiple instances, DI prefers property-name slots plus `Factory` configuration. That keeps instance choice in config for stable cases and leaves runtime selection to `NamedLookupInterface` only when the target name comes from data.

## Container State Stays Process-Scoped {#container-state}

`get()` returns shared instances, so the container is not a request-scoped state store. Mutable request or connection state belongs in `Switon\Core\ContextAware` and `Switon\Core\ContextManagerInterface`, not in DI singletons.

## Bootstrap Belongs to the Framework {#bootstrap-belongs-to-the-framework}

DI publishes `ContainerInterface`, `InjectorInterface`, and `Switon\Di\ServiceProvider`, but bootstrap ordering lives higher up. That keeps DI focused on wiring rules instead of startup sequencing.
