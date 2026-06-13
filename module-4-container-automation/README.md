# Module 4 — Container Automation with PHP-DI
> **PHP 8.5 OOP Mastery Course**
> **Folder:** `module-4-container-automation/`

---

## Why This Module Exists

In Module 3 you built a clean, fully-injected application — but you wired it manually. Every time you add a new service, you must update the composition root. Every time a dependency changes, you must trace the chain and update every affected `new` call.

Module 4 automates that wiring. A **DI container** reads your constructor type-hints using PHP's Reflection API and resolves the entire dependency graph automatically. You add a class and its dependencies are wired without any manual registration.

By the end of this module you will understand — from first principles — how PHP-DI works. You will not just use it as a black box; you will know what it is doing internally, why it exists, and exactly where it belongs in your architecture.

---

## Module Goal

By the end of this module you will be able to:

1. Explain what a service container does and why it is not a Service Locator
2. Use the PHP Reflection API to inspect constructor signatures at runtime
3. Build a minimal auto-wiring container from scratch
4. Configure PHP-DI with explicit bindings for interfaces → concrete classes
5. Use factory definitions, scopes, and environment-based configuration
6. Wire a complete Slim PHP HTTP API using PHP-DI as a PSR-11 container
7. Know where the container belongs in your architecture — and where it does not

---

## ⚠️ The Container Boundary Rule

> **The container belongs at the composition root — the entry point only.**
> **Business logic classes must never call `$container->get(...)` directly.**

A class that calls the container is using it as a **Service Locator** — a global dependency registry that hides coupling. This is the pattern DI was invented to replace. The container's job is to *wire* the graph at startup, not to be called by the graph at runtime.

```
✅  index.php → container builds OrderService(GatewayImpl, MailerImpl, LoggerImpl)
                → OrderService is fully wired, never touches the container

❌  class OrderService {
        public function process(): void {
            $gateway = $this->container->get(GatewayInterface::class); // ← Service Locator
        }
    }
```

This rule is restated in `COURSE_PHILOSOPHY.md` as **Rule 1: Config belongs at the entry point.**

---

## 🗺️ Module Roadmap

```
[Lesson 4.1: Service Containers]
          ↓
[Lesson 4.2: PHP Reflection API]
          ↓
[Lesson 4.3: Auto-wiring]
          ↓
[Lesson 4.4: PHP-DI Library]
          ↓
[Lesson 4.5: Capstone — Slim PHP + PHP-DI]  ⭐
```

---

## Lesson 4.1 — Service Containers

> **What a container is and how to build a minimal one by hand.**

### Topics

- What a service container is: a registry that creates and returns configured objects
- Container as registry (bind once, resolve many times)
- Container as factory (resolve = create fresh every time)
- Service identifiers: using interface class names as keys
- Why the container is not a Service Locator (the calling context matters)
- Building a minimal container: `bind()`, `singleton()`, `make()`

### Key interface (the container contract)
```php
interface ContainerInterface {
    public function get(string $id): mixed;
    public function has(string $id): bool;
}
```

### Examples
```
examples/
  01-manual-container.php          ← bind(), get() from scratch — 50 lines
  02-singleton-registry.php        ← Store and return the same instance
  03-factory-vs-registry.php       ← Same binding, different resolution behaviour
  04-container-vs-locator.php   ← Container vs Service Locator — calling context matters
```

### Challenge
- Build a `SimpleContainer` with `bind()`, `singleton()`, and `make()` methods
- Wire the Module 3 checkout system using only your container

---

## Lesson 4.2 — PHP Reflection API

> **Read constructor signatures at runtime — the foundation of auto-wiring.**

### Topics

- `ReflectionClass` — inspect a class's metadata without instantiating it
- `ReflectionMethod::getParameters()` — read a constructor's parameter list
- `ReflectionParameter::getType()` — read a parameter's type hint as a string
- `ReflectionNamedType::getName()` — get the fully-qualified class name from a type
- Handling parameters with no type hint (primitives that cannot be auto-wired)
- Handling nullable and union types in constructor parameters
- Performance: why real containers cache Reflection results

### The core reflection loop
```php
$refClass  = new ReflectionClass(OrderService::class);
$refCtor   = $refClass->getConstructor();

foreach ($refCtor->getParameters() as $param) {
    $type = $param->getType();
    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
        $depClass = $type->getName(); // e.g. "App\Gateway\PaymentGatewayInterface"
        echo "  Needs: {$depClass}\n";
    }
}
```

### Examples
```
examples/
  01-reflection-basics.php         ← ReflectionClass, methods, properties
  02-reading-constructor-params.php ← ReflectionParameter + type inspection
  03-handling-edge-cases.php        ← Primitives, nullable, union types
  04-caching-reflection.php         ← Why and how to cache reflection results
```

### Challenge
- Write a function `getConstructorDependencies(string $class): array` that returns
  the list of type-hinted class names for every constructor parameter
- Handle: no constructor, primitive params, nullable params

---

## Lesson 4.3 — Auto-wiring

> **Build a container that resolves dependencies recursively without explicit registration.**

### Topics

- Auto-wiring: using Reflection to resolve constructor dependencies automatically
- Recursive resolution: `OrderService → [PaymentGateway, Mailer, Logger]`
  → each of those is also resolved recursively
- Singleton caching during resolution: resolve each class only once per container lifetime
- Circular dependency detection: A needs B needs A → `CircularDependencyException`
- When auto-wiring fails: primitive constructor params (strings, ints, arrays)
- Combining auto-wiring with explicit bindings: auto-wire most things, explicitly bind interfaces

### The auto-wiring algorithm
```
resolve(className):
  if className in cache → return cached instance
  reflect constructor parameters
  for each param:
    if param has a type-hint class → resolve(param type) recursively
    else → throw UnresolvableParameterException
  instantiate className with resolved params
  store in cache
  return instance
```

### Examples
```
examples/
  01-basic-autowiring.php          ← Resolve a 2-level dependency chain
  02-recursive-resolution.php      ← Resolve a 4-level chain automatically
  03-circular-detection.php        ← Detect and report circular dependencies
  04-explicit-fallback.php         ← Explicit binding overrides auto-wiring
```

### Challenge
- Extend your `SimpleContainer` from Lesson 4.1 to support auto-wiring
- Resolve the full checkout system with zero manual `bind()` calls
- Add circular dependency detection

---

## Lesson 4.4 — PHP-DI Library

> **Use a production-grade container with zero-config auto-wiring.**

### Topics

- Installing PHP-DI: `composer require php-di/php-di`
- `ContainerBuilder` — building a container with configuration
- Zero-config auto-wiring: PHP-DI resolves concrete classes automatically
- Explicit bindings: `interface → concrete class` via definitions file
- Factory definitions: `\DI\factory(callable)` for classes with primitive params
- Singleton vs transient scope (covered in depth in Lesson 6.2)
- Reading configuration from environment variables inside definitions

### PHP-DI definition patterns
```php
// config/services.php
use function DI\autowire;
use function DI\factory;
use function DI\env;

return [
    // Interface → concrete class (auto-wired)
    DatabaseInterface::class    => autowire(MySQLDatabase::class),
    LoggerInterface::class      => autowire(FileLogger::class),
    MailerInterface::class      => autowire(SmtpMailer::class),

    // Factory for classes with primitive constructor params
    MySQLDatabase::class => factory(function() {
        return new MySQLDatabase(getenv('DATABASE_URL'));
    }),

    // Environment-based binding
    GatewayInterface::class => factory(function() {
        return getenv('APP_ENV') === 'production'
            ? new StripeGateway(getenv('STRIPE_KEY'))
            : new FakeGateway();
    }),
];
```

### Examples
```
examples/
  01-phpdi-zero-config.php         ← ContainerBuilder + auto-wiring, no definitions
  02-explicit-bindings.php         ← Interface → concrete mappings
  03-factory-definitions.php       ← Factories for env-dependent classes
  04-full-application.php          ← Wire the complete Module 3 system
```

### Challenge
- Wire the complete Module 3 checkout system using PHP-DI
- Replace your manual composition root with a `config/services.php` definitions file
- All five unit tests from Module 5 Lesson 5.2 must still pass

---

## Lesson 4.5 — Capstone: Slim PHP + PHP-DI ⭐
> **Wire a real HTTP API using Slim as the framework and PHP-DI as the PSR-11 container.**

### Topics

- What Slim PHP is: a PSR-7/PSR-15 micro-framework — routing without an ORM or template engine
- Installing Slim: `composer require slim/slim slim/psr7`
- The three-file application structure:
  1. `public/index.php` — the entry point; boots the container and Slim app
  2. `config/services.php` — the PHP-DI definitions file
  3. `src/Http/` — controller classes (auto-wired by PHP-DI via Slim's container bridge)
- Booting PHP-DI as a PSR-11 container inside Slim
- Auto-wiring controllers: Slim resolves controller classes from the container automatically
- The `Config vs Core` separation: all environment variables, DSNs, and API keys in `services.php` only
- Designing the three routes: `GET /products`, `POST /orders`, `GET /orders/{id}`

### Slim + PHP-DI bootstrap pattern
```php
// public/index.php
$builder = new \DI\ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/../config/services.php');
$container = $builder->build();

$app = \Slim\Factory\AppFactory::createFromContainer($container);
require __DIR__ . '/../config/routes.php';
$app->run();
```

### Application structure
```
src/
├── Contracts/
│   ├── LoggerInterface.php
│   └── MailerInterface.php
├── Domain/
│   ├── Product/
│   │   ├── ProductRepositoryInterface.php
│   │   └── InMemoryProductRepository.php
│   └── Order/
│       ├── OrderRepositoryInterface.php
│       ├── OrderService.php
│       └── InMemoryOrderRepository.php
├── Http/
│   ├── ProductController.php    ← auto-wired: __construct(ProductRepositoryInterface $repo)
│   └── OrderController.php      ← auto-wired: __construct(OrderService $service)
└── Infrastructure/
    ├── ConsoleLogger.php
    └── NullMailer.php
config/
├── services.php                 ← ALL bindings and env-dependent config lives here
└── routes.php                   ← Route definitions only
public/
└── index.php                    ← Container build + Slim boot
```

### Challenge
- Build the three-route HTTP API end-to-end
- All controller dependencies must be auto-wired (no manual `new` in controllers)
- Write one integration test per route (Lesson 5.4 style)
- Confirm the `Config vs Core` rule: no `getenv()` call outside `services.php`

---

## ✅ Module 4 Checklist

- [ ] Lesson 4.1 — Service Containers (build one from scratch)
- [ ] Lesson 4.2 — PHP Reflection API
- [ ] Lesson 4.3 — Auto-wiring (extend the container)
- [ ] Lesson 4.4 — PHP-DI Library (wire the Module 3 system)
- [ ] Lesson 4.5 — Capstone: Slim PHP + PHP-DI ⭐

---

## 🛠️ Tools Required for This Module

```bash
# Install PHP-DI
composer require php-di/php-di

# Install Slim (Lesson 4.5)
composer require slim/slim slim/psr7

# Suggested project structure from Lesson 4.5 onwards
composer require slim/slim slim/psr7 php-di/php-di
```

---

## 📖 Reference

- [PHP-DI Documentation](https://php-di.org/doc/)
- [PHP-DI Definitions Reference](https://php-di.org/doc/php-definitions.html)
- [PHP Reflection API Manual](https://www.php.net/manual/en/book.reflection.php)
- [Slim PHP Documentation](https://www.slimframework.com/docs/v4/)
- [PSR-11 Container Interface](https://www.php-fig.org/psr/psr-11/)