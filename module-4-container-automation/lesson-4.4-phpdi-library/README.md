# Lesson 4.4 — PHP-DI Library
> **Module 4: Container Automation with PHP-DI** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-4.4-phpdi-library/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-phpdi-zero-config.php           ← ContainerBuilder + auto-wiring, no definitions
│   ├── 02-explicit-bindings.php           ← Interface → concrete mappings
│   ├── 03-factory-definitions.php         ← Factories for env-dependent classes
│   └── 04-full-application.php            ← Wire the complete Module 3 system
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter.php                        ← Working file
│   └── solution.php                       ← Reference solution
│
└── quiz/
    └── QUIZ.md
```

---

## 0 — Before You Begin: Install PHP-DI

```bash
composer require php-di/php-di
```

Verify:
```bash
php -r "require 'vendor/autoload.php'; echo \DI\ContainerBuilder::class . PHP_EOL;"
# DI\ContainerBuilder
```

Every example in this lesson starts with `require __DIR__ . '/../vendor/autoload.php';`
(adjust the path to match where your `vendor/` folder lives relative to the example file).

---

## 1 — What PHP-DI Is

PHP-DI is a production-grade DI container that does everything the `AutowiringContainer` from Lesson 4.3 does — plus:

| Feature | Our AutowiringContainer | PHP-DI |
|---------|------------------------|--------|
| Auto-wiring via Reflection | ✓ | ✓ |
| Explicit interface bindings | ✓ | ✓ |
| Singleton caching | ✓ | ✓ |
| Circular dependency detection | ✓ | ✓ |
| Factory definitions (for primitive params) | Basic | ✓ Full |
| Transient scope (fresh per-resolution) | ✗ | ✓ |
| PSR-11 compliance | ✗ | ✓ |
| Compiled container (zero Reflection at runtime) | ✗ | ✓ |
| Lazy proxies | ✗ | ✓ |
| Framework integrations (Slim, Symfony) | ✗ | ✓ |

---

## 2 — ContainerBuilder

`ContainerBuilder` is PHP-DI's entry point. It builds a container from configuration:

```php
use DI\ContainerBuilder;

$builder = new ContainerBuilder();

// Optional: add a definitions file
$builder->addDefinitions(__DIR__ . '/config/services.php');

// Optional: enable compiled container for production
// $builder->enableCompilation(__DIR__ . '/var/cache');

// Build the container
$container = $builder->build();
```

The resulting `$container` implements PSR-11's `Psr\Container\ContainerInterface`.

---

## 3 — Zero-Config Auto-wiring

PHP-DI auto-wires concrete classes with zero configuration:

```php
$builder   = new ContainerBuilder();
$container = $builder->build();

// No definitions added — PHP-DI reads constructor type hints and wires automatically
$service = $container->get(OrderService::class);
```

For concrete-class-only dependency graphs (no interfaces), zero config is sufficient.
In practice, at least the interface→concrete bindings are needed.

---

## 4 — Definitions File

The definitions file is a PHP file that returns an array. It is the composition root for PHP-DI.

**Course Philosophy Rule 1: Config belongs at the entry point.**
All `getenv()` calls, DSNs, API keys, and environment-based decisions live in this file — and nowhere else.

```php
// config/services.php
<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\factory;
use function DI\create;

return [
    DatabaseInterface::class => autowire(InMemoryDatabase::class),
    LoggerInterface::class   => autowire(ConsoleLogger::class),
    MailerInterface::class   => autowire(ConsoleMailer::class),
];
```

---

## 5 — PHP-DI Definition Functions

### `autowire(string $className)`
Binds to a concrete class. PHP-DI auto-wires its constructor:
```php
DatabaseInterface::class => autowire(MySQLDatabase::class),
```

### `factory(callable $factory)`
Calls a factory callable. Use for classes with primitive constructor params or env-dependent logic:
```php
MySQLDatabase::class => factory(function () {
    return new MySQLDatabase(
        getenv('DATABASE_URL') ?: 'sqlite::memory:',
        getenv('DB_USER')      ?: 'root'
    );
}),
```

The factory receives the container as its first argument, so it can resolve other services:
```php
LoggingGateway::class => factory(function (\Psr\Container\ContainerInterface $c) {
    return new LoggingGateway(
        $c->get(StripeGateway::class),
        $c->get(LoggerInterface::class)
    );
}),
```

### `create(string $className)`
Similar to `autowire()` but allows explicit constructor parameter overrides:
```php
FileLogger::class => create(FileLogger::class)
    ->constructor(getenv('LOG_PATH') ?: '/tmp/app.log'),
```

### Environment-based conditional:
```php
GatewayInterface::class => factory(function () {
    return getenv('APP_ENV') === 'production'
        ? new StripeGateway(getenv('STRIPE_KEY'))
        : new FakeGateway();
}),
```

---

## 6 — PSR-11 Compliance

PHP-DI implements `Psr\Container\ContainerInterface`:

```php
interface ContainerInterface {
    public function get(string $id): mixed;
    public function has(string $id): bool;
}
```

Any framework that accepts a PSR-11 container (Slim, Symfony, Mezzio) works with PHP-DI directly.

---

## 7 — The Container Boundary Rule (Rule 1 Revisited)

PHP-DI's `$container->get()` belongs **only** in:
- `index.php` / bootstrap files
- Framework integration points (`AppFactory::createFromContainer($container)`)
- Test bootstrap files

Never inside business logic classes.

```php
// ✅ Correct — entry point only
$container  = $builder->build();
$controller = $container->get(CheckoutController::class);
$controller->handle($request); // never touches container again

// ❌ Wrong — Service Locator inside business logic
class CheckoutController {
    public function __construct(private \DI\Container $container) {}
    public function handle(): void {
        $service = $this->container->get(CheckoutService::class); // ← Service Locator
    }
}
```

---

## 8 — Quick Reference

```php
// Install
composer require php-di/php-di

// Bootstrap (index.php / entry point)
use DI\ContainerBuilder;
$builder   = new ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/config/services.php');
$container = $builder->build();

// config/services.php
use function DI\autowire;
use function DI\factory;
use function DI\create;

return [
    // Interface → concrete (auto-wired constructor)
    SomeInterface::class => autowire(ConcreteClass::class),

    // Factory for primitive params or env logic
    AnotherClass::class => factory(function() {
        return new AnotherClass(getenv('SOME_KEY'));
    }),

    // Create with explicit constructor param
    LogClass::class => create(LogClass::class)
        ->constructor(getenv('LOG_PATH') ?: '/tmp/app.log'),
];

// Resolve
$service = $container->get(SomeInterface::class);
$container->has(SomeInterface::class); // bool
```

---

## ✅ Lesson Checklist

- [ ] Run `composer require php-di/php-di`
- [ ] Read this README fully — especially Sections 5 (definition functions) and 7 (container boundary rule)
- [ ] Run and study `examples/01-phpdi-zero-config.php`
- [ ] Run and study `examples/02-explicit-bindings.php`
- [ ] Run and study `examples/03-factory-definitions.php`
- [ ] Run and study `examples/04-full-application.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **4.5 — Capstone: Slim PHP + PHP-DI** — wire a real HTTP API using PHP-DI as a PSR-11 container inside Slim.*