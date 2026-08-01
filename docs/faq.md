# DI FAQ {#di-faq}

Short answers for common questions. Start with [Quick Start](README.md#quick-start), then return here when something needs checking.

> **Prerequisites:** [DI Package](README.md)

## Getting Started {#getting-started}

### How do I inject a dependency? {#inject-dependency}

Declare a typed `#[Autowired]` property and let the container create the object. See [Quick Start](README.md#quick-start).

### When does auto-resolution work? {#when-auto-resolution-works}

It works when `XxxInterface` and `Xxx` live in the same namespace. See [Interface-First Autowiring](README.md#interface-first-autowiring).

### How do I inject scalars or arrays? {#inject-scalars-or-arrays}

Use class config or `make()` parameters. For array merge rules and `instances: true`, see [Autowired](autowired.md#autowired).

### How do I choose between `get()` and `make()`? {#choose-get-or-make}

Use `get()` for shared services and `make()` for fresh per-use objects. See [Shared Versus Fresh](README.md#shared-versus-fresh).

## Named Instances {#named-instances}

### How do I inject one of several instances? {#multiple-instances}

Use `Switon\Di\Factory` when the slot is stable in code, or `NamedLookupInterface` when the name is only known at runtime. See [Named Slots](README.md#named-slots).

### Does `?Type` make object injection optional? {#optional-dependency-shape}

No. `?Type` changes the PHP type only. Use `Type|Lazy` when you want deferred resolution.

## Troubleshooting {#troubleshooting}

### Why did `#[Autowired]` stay `null`? {#autowired-null}

The object was probably built with `new` instead of by the container or injector. See [Keep in Mind](README.md#keep-in-mind).

### How do I inspect the container? {#inspect-container}

Use `bash bin/console container:inspect --id 'App\Service\ProductService'` for one service, `bash bin/console container:definitions --filter 'App\Service'` for definitions, and `bash bin/console container:instances --filter 'App\Service'` for resolved singletons.

### Which exceptions matter first? {#important-exceptions}

Start with `NotFoundException`, `MissingConfigurationException`, `MissingTypeDeclarationException`, `InterfaceAutowiringException`, and `ServiceAlreadyResolvedException`. See [README](README.md#keep-in-mind) for the main failure types.
