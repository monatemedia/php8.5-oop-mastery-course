# Lesson 4.1 — Service Containers
> **Module 4: Container Automation with PHP-DI** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-4.1-service-containers/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-manual-container.php            ← bind(), get() from scratch (~50 lines)
│   ├── 02-singleton-registry.php          ← One instance shared across the whole graph
│   ├── 03-factory-vs-registry.php         ← Fresh instance vs shared instance
│   └── 04-container-vs-locator.php        ← Why the calling context is everything
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter.php
│   └── solution.php
│
└── quiz/
    └── QUIZ.md
```

---

## 1 — What Problem Does a Container Solve?

In Lesson 3.4 you built a flat IoC wiring function:

```php
function buildApp(): OrderController {
    $db         = new InMemoryDatabase();
    $logger     = new ConsoleLogger();
    $mailer     = new ConsoleMailer();
    $repository = new ProductRepository($db, $logger);
    $service    = new OrderService($repository, $mailer, $logger);
    return new OrderController($service, $logger);
}
```

This is correct IoC — but it does not scale. At 50+ services:
- Every new class requires a new `$x = new X(...)` line in the right order
- `$logger` appears in 40 constructor calls
- Change a constructor parameter → manually update the wiring file

A **service container** solves this by storing the wiring instructions separately from the application code, and providing a central place to retrieve correctly-configured objects.

---

## 2 — What Is a Service Container?

A service container is an **object registry** with two responsibilities:

1. **Store bindings** — instructions for how to build a service
2. **Resolve requests** — build the service (with all its dependencies) when asked

```php
$container = new Container();

// Store: "when someone asks for DatabaseInterface, give them MySQLDatabase"
$container->bind(DatabaseInterface::class, fn() => new MySQLDatabase(getenv('DB_DSN')));

// Resolve: build and return a correctly-configured MySQLDatabase
$db = $container->get(DatabaseInterface::class);
```

The container hides the construction details. The caller asks for an interface; the container decides which concrete class to build and how.

---

## 3 — Three Resolution Modes

### Mode 1 — Factory (fresh instance every time)

```php
$container->bind(ShoppingCart::class, fn() => new ShoppingCart());

$cart1 = $container->get(ShoppingCart::class); // new instance
$cart2 = $container->get(ShoppingCart::class); // another new instance
var_dump($cart1 === $cart2); // false — different objects
```

Use for objects that must be fresh per request (shopping carts, per-user sessions).

### Mode 2 — Singleton (shared instance)

```php
$container->singleton(DatabaseInterface::class, fn() => new MySQLDatabase(getenv('DB_DSN')));

$db1 = $container->get(DatabaseInterface::class); // created once
$db2 = $container->get(DatabaseInterface::class); // same instance returned
var_dump($db1 === $db2); // true — same object
```

Use for expensive or stateless infrastructure: database connections, loggers, mailers.

### Mode 3 — Instance (pre-built object)

```php
$logger = new FileLogger(getenv('LOG_PATH'));
$container->instance(LoggerInterface::class, $logger); // store a pre-built object
```

Use when you need full control over construction before registering.

---

## 4 — Service Identifiers

Containers use **string keys** to look up services. The most common conventions:

```php
// ✅ Interface name as key (recommended — type-safe, readable)
$container->bind(DatabaseInterface::class, fn() => new MySQLDatabase(...));

// ✅ Concrete class name as key (for classes with no interface)
$container->bind(OrderService::class, fn() => new OrderService(...));

// ❌ Arbitrary string key (avoid — breaks auto-wiring in Lesson 4.3)
$container->bind('database', fn() => new MySQLDatabase(...));
```

Using interface names as keys is the convention PHP-DI follows. When the auto-wiring container (Lesson 4.3) reads `private DatabaseInterface $db` from a constructor, it looks up `DatabaseInterface::class` in the bindings — exactly the key you registered.

---

## 5 — Container vs Service Locator

This is the most important conceptual distinction in this module.

**A container and a Service Locator use the same technology.** The difference is entirely in *where* they are called.

```
CONTAINER (correct):
  Entry point              Business classes
  ────────────             ────────────────
  $container
    ->get(OrderController) → new OrderController($service, $logger)
                               $service receives $db, $mailer, $logger
                               (all wired by the container at boot)

  Business classes NEVER touch $container.
  They receive fully-wired dependencies via constructor.


SERVICE LOCATOR (anti-pattern):
  Business class calls the container directly at runtime:
  class OrderService {
      public function process(): void {
          $db = $container->get(DatabaseInterface::class); // ← WRONG
      }
  }
```

| | Container (correct) | Service Locator (anti-pattern) |
|--|--------------------|---------------------------------|
| Where is `get()` called? | Entry point / bootstrap | Inside business logic classes |
| Are dependencies visible? | Yes — in constructor signatures | No — hidden inside methods |
| Is the class testable? | Yes — inject fakes via constructor | Hard — must pre-populate the global container |
| Does it hide coupling? | No | Yes |

**Rule:** `$container->get(...)` belongs only in `index.php`, `bootstrap.php`, or a framework provider. Never inside a business logic class.

---

## 6 — PSR-11: The Standard Container Interface

PSR-11 defines the standard PHP container interface, implemented by PHP-DI, Symfony's container, Laravel's container, and many others:

```php
namespace Psr\Container;

interface ContainerInterface {
    public function get(string $id): mixed;
    public function has(string $id): bool;
}
```

Any code that type-hints against `ContainerInterface` (rather than a specific container library) can work with any PSR-11 compliant container. Slim PHP, for example, accepts any `ContainerInterface` — which is why PHP-DI integrates with it cleanly.

---

## 7 — The Container in the Architecture

```
┌─────────────────────────────────────────────────────┐
│  index.php (entry point / composition root)         │
│                                                      │
│  $container = buildContainer($config);               │
│  $app = new App($container);    ← bootstrapped here  │
│  $app->run();                                         │
└───────────────────────┬─────────────────────────────┘
                        │ container wires...
         ┌──────────────┼──────────────┐
         ▼              ▼              ▼
   OrderController  UserService  ProductRepo
   (constructor     (constructor  (constructor
   injected)        injected)     injected)
         │
         ▼
   Business logic runs — never touches $container
```

The container exists at the top. It wires everything at boot. After that, it is irrelevant to the running application.

---

## 8 — Quick Reference

```php
// Build a container
$container = new Container();

// Bind: factory (fresh instance every get())
$container->bind(ShoppingCart::class, fn() => new ShoppingCart());

// Bind: singleton (same instance every get())
$container->singleton(LoggerInterface::class, fn() => new FileLogger('/var/log/app.log'));

// Bind: pre-built instance
$container->instance(DatabaseInterface::class, new MySQLDatabase(getenv('DB_DSN')));

// Resolve
$logger = $container->get(LoggerInterface::class);

// Check existence
$container->has(LoggerInterface::class); // true

// Key convention: always use interface class names
DatabaseInterface::class // → 'App\Contracts\DatabaseInterface'
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 5 (container vs locator) and 6 (PSR-11)
- [ ] Run and study `examples/01-manual-container.php`
- [ ] Run and study `examples/02-singleton-registry.php`
- [ ] Run and study `examples/03-factory-vs-registry.php`
- [ ] Run and study `examples/04-container-vs-locator.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **4.2 — PHP Reflection API** — reading constructor signatures at runtime, the foundation of auto-wiring.*