# Switon DI Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/di/ci.yml?branch=main&label=CI)](https://github.com/switon-php/di/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's dependency injection container for autowiring, named services, and bootstrap wiring.

## Highlights

- **Shared container:** `ContainerInterface` handles service resolution and definition registration.
- **Service autowiring:** `#[Autowired]` can inject object services without manual lookups.
- **Config autowiring:** the same attribute can fill scalar and array properties from config.
- **Named instances:** `FactoryInterface` supports multiple implementations of the same type.
- **Lazy dependencies:** `Lazy` defers non-default resolution until a value is actually used.
- **Autowired instances:** `#[Autowired(instances: true)]` can inject service arrays from config.

## Installation

```bash
composer require switon/di
```

## Quick Start

```yaml
# switon.yml
Switon\Core\AppInterface:
  id: admin
  name: Admin Panel
  version: 2.0.0
  env: dev
  debug: true
  timezone: Asia/Shanghai
```

```php
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;

class DashboardService
{
    #[Autowired] protected AppInterface $app;

    public function title(): string
    {
        return $this->app->name() . ' (' . $this->app->env() . ')';
    }
}
```

Docs: https://docs.switon.dev/latest/di

## License

MIT.
