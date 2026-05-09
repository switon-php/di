# Switon DI Package

Dependency injection container and autowiring for Switon Framework.

## Installation

```bash
composer require switon/di
```

**Requirements:** PHP 8.3+, `ext-json`

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Di\Container;
use Switon\Di\ServiceProvider;

interface CacheInterface {}
class Cache implements CacheInterface {}

class ProductService
{
    #[Autowired] protected CacheInterface $cache;
}

$container = new Container();
(new ServiceProvider())->register($container);
$productService = $container->get(ProductService::class);
```

Docs: https://docs.switon.dev/latest/di

## License

MIT.
