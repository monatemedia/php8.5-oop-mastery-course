# Module 6 — Object Lifecycle & State Management
> **PHP 8.5 OOP Mastery Course**
> **Folder:** `module-6-object-lifecycle-and-state/`

---

## Why This Module Exists

By the end of Module 5 you can build, wire, and test a well-designed PHP application. But there is one class of production bug that clean design, DI, and unit tests do not protect you from: **state leaking between requests**.

PHP is a share-nothing language — in a typical FPM or CLI setup, every request starts a fresh process. But when you introduce long-running processes (Swoole, FrankenPHP, ReactPHP, queue workers, RoadRunner), or when you use PHP-DI's singleton scope carelessly, your objects can **outlive the request they were created for**. State that was meant to be per-request becomes global — silently, invisibly, catastrophically.

This module teaches you to reason about **object lifetime**: how long does this object live? Does it hold state? Can that state become stale or bleed into the next request? How do I design services so that they are safe regardless of how the runtime wires them?

---

## Module Goal

By the end of this module you will be able to:

1. Explain PHP's share-nothing architecture and when it breaks down
2. Distinguish transient from singleton scope in PHP-DI and choose correctly
3. Identify stateful services that are dangerous as singletons
4. Redesign stateful services to be stateless or to manage their own lifecycle explicitly
5. Use PHP-DI factory definitions for objects that must be created fresh each time
6. Recognise the five lifecycle anti-patterns and fix them

---

## 🗺️ Module Roadmap

```
[Lesson 6.1: PHP's Share-Nothing Architecture]
            ↓
[Lesson 6.2: Transient vs Singleton Scopes in PHP-DI]
            ↓
[Lesson 6.3: The Danger of Stateful Services]
            ↓
[Lesson 6.4: Designing Stateless Services]
            ↓
[Lesson 6.5: Factory Definitions for Complex Lifecycles]
```

---

## Lesson 6.1 — PHP's Share-Nothing Architecture

> **Understand the default memory model before learning when it breaks.**

### Topics

- How PHP-FPM works: one worker process per request, all memory freed at request end
- Why "fresh every request" makes PHP naturally safe: no shared memory between requests
- The three runtime models where share-nothing breaks:
  1. **Swoole / FrankenPHP / RoadRunner** — long-running PHP worker that handles many requests
  2. **Queue workers** — `php artisan queue:work` runs indefinitely, same process, many jobs
  3. **CLI scripts** — explicit state accumulation risk in long-running scripts
- How to tell if you are running in a share-nothing vs persistent context
- Why this module matters even if you use PHP-FPM — singletons in PHP-DI survive the container's lifetime

### Key concepts
- **Request lifecycle** — the period from HTTP request received to response sent
- **Worker lifecycle** — the period from worker process start to worker process death
- **Object scope** — how long a specific object instance exists relative to the lifecycle

### Examples
```
examples/
  01-share-nothing-demo.php        ← Simulates per-request fresh state vs persistent state
  02-long-running-worker.php       ← Shows state accumulation in a simulated worker loop
  03-spotting-your-runtime.php     ← How to detect the execution context at runtime
```

---

## Lesson 6.2 — Transient vs Singleton Scopes in PHP-DI

> **The container's two most important decisions: create once or create every time.**

### Topics

- **Transient scope** (`\DI\create()`) — a new instance every time the container resolves the binding
- **Singleton scope** (the PHP-DI default with auto-wiring) — one instance per container lifetime, shared everywhere
- How to declare each scope explicitly in PHP-DI definitions
- The mental model: singleton = shared, transient = fresh
- Which dependencies are safe as singletons: stateless services, loggers, DB connections (connection pooling handled externally)
- Which dependencies are dangerous as singletons: anything that accumulates state between calls

### PHP-DI scope syntax
```php
use function DI\create;
use function DI\autowire;
use function DI\factory;

return [
    // Singleton (default) — created once, shared everywhere
    LoggerInterface::class => autowire(FileLogger::class),

    // Transient — new instance every resolution
    ShoppingCartInterface::class => \DI\decorate(function() {
        return new ShoppingCart();
    }),

    // Factory — explicit transient using a callable
    RequestContextInterface::class => factory(function() {
        return new RequestContext($_SERVER['REQUEST_URI'] ?? '/');
    }),
];
```

### Examples
```
examples/
  01-singleton-vs-transient.php    ← Same class, two scopes — shows shared vs fresh
  02-safe-singletons.php           ← Logger, DB connection — stateless, safe to share
  03-dangerous-singletons.php      ← Shopping cart as singleton — state bleeds between users
```

---

## Lesson 6.3 — The Danger of Stateful Services

> **The five lifecycle anti-patterns that cause production bugs.**

### Topics

The five anti-patterns, each with a before/after code example and a test that catches the bug:

**Anti-pattern 1 — Accumulating service** (the most common)
```php
// ❌ ReportService accumulates results across calls
class ReportService {
    private array $results = []; // ← DANGER: persists between requests as singleton

    public function addResult(array $row): void {
        $this->results[] = $row; // grows forever in a long-running process
    }

    public function getResults(): array { return $this->results; }
}
```

**Anti-pattern 2 — Authentication state stored on a service**
```php
// ❌ AuthService remembers the current user as instance state
class AuthService {
    private ?User $currentUser = null; // ← DANGER: User A's session leaks to User B

    public function login(User $user): void { $this->currentUser = $user; }
    public function getUser(): ?User { return $this->currentUser; }
}
```

**Anti-pattern 3 — Request-scoped data on a singleton**
```php
// ❌ RequestLogger stores the request ID on itself
class RequestLogger {
    private string $requestId = ''; // set per-request, but singleton persists

    public function setRequestId(string $id): void { $this->requestId = $id; }
    public function log(string $msg): void { echo "[{$this->requestId}] {$msg}"; }
}
```

**Anti-pattern 4 — Counter/statistics on a singleton**
```php
// ❌ Counts requests — correct for a statistic but wrong as a singleton if the worker restarts
class RequestCounter {
    private int $count = 0;
    public function increment(): void { $this->count++; }
    public function get(): int { return $this->count; }
}
```

**Anti-pattern 5 — Deferred initialisation that never resets**
```php
// ❌ CacheWarmer initialises once, never refreshes
class CacheWarmer {
    private bool $warmed = false;
    public function warm(): void {
        if ($this->warmed) return; // ← silently skips on second request
        // ... warm the cache
        $this->warmed = true;
    }
}
```

### How to spot stateful services in code review
- Any `private` property that is written by a public method
- Any property initialised to `[]`, `0`, `false`, or `null` with a setter
- Any `bool $initialised` flag
- Any `array $accumulated` or `array $results` property

### Examples
```
examples/
  01-accumulating-service.php      ← Anti-pattern 1 demonstrated and caught
  02-auth-state-leak.php           ← Anti-pattern 2: user A's data leaks to user B simulation
  03-all-five-antipatterns.php     ← All five, each with the test that exposes the bug
```

---

## Lesson 6.4 — Designing Stateless Services

> **Eliminate instance state. Pass everything through method parameters.**

### Topics

- The stateless service rule: **same inputs always produce same outputs, no side effects on the object itself**
- Refactoring the five anti-patterns from Lesson 6.3 to be stateless
- Moving state to the right place:
  - Per-request state → pass as method parameter or inject via transient-scoped factory
  - Accumulated results → return from method, accumulate at the call site
  - Auth state → inject `RequestContextInterface` (per-request transient)
  - Statistics → push to an external store (Redis, DB), not instance memory
- Value objects and immutability — when holding state IS correct
- The `readonly` property and immutable data transfer

### Refactoring the accumulating service
```php
// ❌ BEFORE: stateful service
class ReportService {
    private array $results = [];
    public function addResult(array $row): void { $this->results[] = $row; }
    public function getResults(): array { return $this->results; }
}

// ✅ AFTER: stateless — caller accumulates the results
class ReportService {
    public function processRow(array $row): array {
        // Transform and validate the row, return it — don't store it
        return ['processed' => true, 'data' => $row];
    }

    public function summarise(array $processedRows): array {
        return ['total' => count($processedRows), 'rows' => $processedRows];
    }
}

// Caller accumulates:
$results = [];
foreach ($rawRows as $row) {
    $results[] = $reportService->processRow($row);
}
$summary = $reportService->summarise($results);
```

### Examples
```
examples/
  01-making-services-stateless.php   ← Refactoring each anti-pattern from Lesson 6.3
  02-request-context-injection.php   ← Per-request data via transient factory
  03-immutable-value-objects.php     ← When state is correct: readonly + value objects
```

### Challenge
- Given five stateful services, identify which anti-pattern each represents
- Refactor all five to be stateless
- Write a test for each that would have caught the original bug
- Confirm all tests pass after refactoring

---

## Lesson 6.5 — Factory Definitions for Complex Lifecycles

> **When auto-wiring is not enough: take control of object construction.**

### Topics

- When `autowire()` is insufficient:
  - The object needs a constructor argument that is not a type-hinted class (e.g. a string, an integer, an array)
  - The object must be constructed fresh every time it is resolved (transient)
  - The object's construction depends on runtime data (current user, current request)
  - The object wraps another object (decorator pattern)
- PHP-DI factory definitions: `\DI\factory(callable $factory)`
- The decorator pattern in PHP-DI: wrapping one binding around another
- Contextual bindings: different implementations for different consumers
- Environment-based bindings: `APP_ENV=production` → `RealGateway`, `APP_ENV=test` → `FakeGateway`

### Factory definition examples
```php
return [
    // Factory for a class needing a runtime string
    PDO::class => \DI\factory(function() {
        return new PDO(getenv('DATABASE_URL'));
    }),

    // Transient factory — new instance every resolution
    ShoppingCart::class => \DI\factory(function() {
        return new ShoppingCart();
    }),

    // Decorator: LoggingGateway wraps the real gateway
    PaymentGatewayInterface::class => \DI\factory(function(
        StripeGateway $real,
        LoggerInterface $logger
    ) {
        return new LoggingGateway($real, $logger);
    }),

    // Environment-based binding
    MailerInterface::class => \DI\factory(function() {
        return getenv('APP_ENV') === 'production'
            ? new SmtpMailer(getenv('SMTP_HOST'), (int) getenv('SMTP_PORT'))
            : new LogMailer();
    }),
];
```

### Examples
```
examples/
  01-factory-basics.php               ← Simple factory for a class with a non-type-hinted arg
  02-transient-factories.php          ← Shopping cart as transient
  03-decorator-in-container.php       ← LoggingGateway wraps StripeGateway via factory
  04-environment-bindings.php         ← Production vs test wiring via APP_ENV
```

### Challenge
- Wire a full application with four factory definitions:
  1. PDO factory using `DATABASE_URL` from env
  2. Transient `ShoppingCart` factory
  3. `LoggingGateway` decorator wrapping `StripeGateway`
  4. Environment-based `MailerInterface` binding
- Write an integration test that verifies the decorator logs correctly

---

## ✅ Module 6 Checklist

- [ ] Lesson 6.1 — PHP's Share-Nothing Architecture
- [ ] Lesson 6.2 — Transient vs Singleton Scopes in PHP-DI
- [ ] Lesson 6.3 — The Danger of Stateful Services (identify the 5 anti-patterns)
- [ ] Lesson 6.4 — Designing Stateless Services (refactor all 5)
- [ ] Lesson 6.5 — Factory Definitions for Complex Lifecycles

---

## The Five Lifecycle Anti-Patterns — Quick Reference

| Anti-pattern | Symptom | Fix |
|-------------|---------|-----|
| Accumulating service | `private array $results = []` written by a public method | Return from method; accumulate at call site |
| Auth state on singleton | `private ?User $currentUser` set by `login()` | Inject `RequestContextInterface` (transient) |
| Request data on singleton | `private string $requestId` set per-request | Pass as method argument or use transient scope |
| Counter/statistics on singleton | `private int $count` incremented by public method | Push to external store (Redis, DB) |
| Deferred initialisation | `private bool $warmed = false` with a reset gap | Use `lazy()` or PHP-DI's built-in lazy proxies |

---

## 📖 Reference

- [PHP-DI Scopes Documentation](https://php-di.org/doc/scopes.html)
- [PHP-DI Factory Definitions](https://php-di.org/doc/php-definitions.html#factories)
- [PHP-DI Decorators](https://php-di.org/doc/php-definitions.html#decorators)
- [FrankenPHP Worker Mode](https://frankenphp.dev/docs/worker/)
- [Swoole Coroutines and State Isolation](https://wiki.swoole.com/#/coroutine)
- [Martin Fowler — Stateless Services](https://martinfowler.com/articles/injection.html)