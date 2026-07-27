# Lesson 6.2 — Transient vs Singleton Scopes in PHP-DI
> **Module 6: Object Lifecycle & State Management** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-6.2-transient-vs-singleton-scopes/
├── README.md                                      ← Theory (you are here)
│
├── examples/
│   ├── 01-singleton-vs-transient.php              ← Same class, two scopes — shared vs fresh
│   ├── 02-safe-singletons.php                     ← Logger, DB connection — stateless, safe to share
│   └── 03-dangerous-singletons.php                ← Shopping cart as singleton — state bleeds between users
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── ScopeAssignmentTest.php                ← Scaffold — assign correct scopes to 6 services
│   └── solution/
│       └── ScopeAssignmentTest.php                ← Full solution with commentary
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — Sections 3 and 4 (the PHP-DI scope syntax and the decision rule) are the core.
2. Run each example with `./vendor/bin/phpunit` and read every comment.
3. Work through the challenge: given six service classes, assign each the correct PHP-DI scope, write a test proving the scope works as intended, and explain your reasoning.
4. Take the quiz cold.

---

## 1 — Why Scope Exists

In Lesson 6.1 you saw what goes wrong when a stateful service lives too long. The root cause in every case was not the service code itself — it was the **scope** (lifetime) assigned to the service in the DI container. Change the scope, and the bug disappears.

PHP-DI controls scope through its definition API. The two scopes you need to master:

| Scope | How you get it in PHP-DI | Object lifetime | When to use |
|-------|--------------------------|-----------------|-------------|
| **Singleton** | any definition resolved with `$container->get()` | One instance per container lifetime | Stateless services, shared infrastructure |
| **Transient** | `$container->make()` | New instance on every call | Anything that holds per-request or per-job state |

> **PHP-DI does not have scopes.** This surprises people, so it is worth stating plainly before
> you read any further: PHP-DI removed the scope concept in version 6. **Every** entry is created
> once and shared — `autowire()`, `create()` and `factory()` alike. Changing the *definition*
> style does not change the lifetime.
>
> Transience comes from the **call**, not the definition: `$container->make($name)` builds a new
> instance every time, and it is the only built-in way to get one. Assume otherwise and you will
> write a `factory()` to "fix" a leaking service and watch it go on leaking.
>
> The two ideas — shared versus fresh — are what actually matter, and they exist in every
> container. This lesson teaches the decision; PHP-DI's particular API is just how you spell it.

---

## 2 — Singleton Scope (the PHP-DI Default)

When you type-hint a dependency and let PHP-DI auto-wire it, the result is a singleton. One instance is created the first time something asks for that class; every subsequent request for the same class receives the same instance.

```php
// PHP-DI auto-wiring (implicit singleton)
$container = new DI\Container();

$a = $container->get(FileLogger::class);
$b = $container->get(FileLogger::class);

var_dump($a === $b); // bool(true) — same instance
```

You can also declare it explicitly in a definitions file:

```php
use function DI\autowire;
use function DI\create;

return [
    // Explicit singleton — same behaviour as auto-wiring
    LoggerInterface::class => autowire(FileLogger::class),

    // create() is also singleton by default
    DatabaseConnection::class => create(DatabaseConnection::class)
        ->constructor(DI\env('DATABASE_URL')),
];
```

### What makes a class safe as a singleton?

A class is safe as a singleton if and only if it is **effectively immutable after construction**:
- All properties are set in the constructor and never changed by public methods
- No property accumulates data between calls
- No property stores "current context" (current user, current request ID, current job)
- Method outputs depend only on method inputs, not on accumulated object state

If a class satisfies all four conditions, it is **stateless** — and stateless classes are always safe as singletons.

---

## 3 — Transient Scope

A transient service returns a fresh instance every time you ask for one. No sharing, no
persistence between requests for it.

In PHP-DI you ask with **`make()`**, not `get()`:

```php
$cart1 = $container->make(ShoppingCartInterface::class);
$cart2 = $container->make(ShoppingCartInterface::class);

var_dump($cart1 === $cart2);   // bool(false) — different instances
```

`make()` resolves the definition and its dependencies exactly as `get()` does, but skips the
shared-instance cache both on the way in and on the way out: the object it returns is new, and it
is not stored for anyone else.

### The trap

The definition style has nothing to do with it. All three of these are shared when resolved with
`get()`:

```php
return [
    A::class => autowire(A::class),
    B::class => create(B::class),
    C::class => factory(fn() => new C()),   // still ONE instance
];
```

```php
$c1 = $container->get(C::class);
$c2 = $container->get(C::class);
var_dump($c1 === $c2);   // bool(true) — the callable ran ONCE
```

`factory()` describes *how to build* the object. It says nothing about *how many* to build. Read
it as "here is a recipe the container could not have guessed", not as "make me a new one each
time" — the second reading is extremely common and quietly wrong.

### So what do you do about a stateful service?

Three real options, in the order you should consider them:

1. **Redesign it so it holds no mutable state.** Then the question evaporates and the shared
   instance is correct. This is Lesson 6.4, and it is the answer most of the time.
2. **Resolve it with `make()`** where a fresh one is genuinely needed — typically at the edge, in
   a controller or a job handler.
3. **Build a fresh container per request.** Under PHP-FPM this is what already happens, which is
   why so much code gets away with being wrong about all of this.

What is *not* an option is writing `factory()` and assuming the problem is solved.

---

## 4 — The Scope Decision Rule

Apply this rule to every service before registering it in the container:

```
Does this class have any property that is written by a public method?
    YES → It holds mutable state. Ask: should that state be shared or fresh?
        Shared across the whole application  → external store (Redis/DB), not instance state
        Fresh per request / per job          → TRANSIENT scope
    NO  → It is effectively stateless after construction → SINGLETON scope (safe)
```

In practice, scan for these red flags — any of them means the class needs careful thought before being made a singleton:

- `private array $something = []` with an `add*()` or `append*()` method
- `private ?SomeType $current = null` with a `set*()` or `login()` method
- `private bool $initialised = false` with an `init()` or `warm()` method
- `private int $count = 0` with an `increment()` method
- `private string $context = ''` with a `setContext()` method

None of these are inherently wrong — they are perfectly fine in a transient or per-request object. They are dangerous only when the object is shared as a singleton in a persistent-worker context.

---

## 5 — Safe Singletons: What They Look Like

### Logger

```php
class FileLogger implements LoggerInterface
{
    // Constructor sets the file path once — never changes
    public function __construct(private readonly string $path) {}

    // log() reads $this->path (immutable) and writes to disk
    // Does NOT accumulate anything on $this
    public function log(string $level, string $message): void
    {
        file_put_contents($this->path, "[{$level}] {$message}\n", FILE_APPEND);
    }
}
```

`FileLogger` is safe as a singleton: its only property (`$path`) is set once at construction and never changed. Every `log()` call is independent — no state accumulates on the object.

### Database connection

```php
class DatabaseConnection
{
    private \PDO $pdo;

    public function __construct(string $dsn, string $user, string $pass)
    {
        // The PDO connection is established once at construction
        // and reused (connection pooling is handled by the DB driver)
        $this->pdo = new \PDO($dsn, $user, $pass);
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

The `DatabaseConnection` is safe as a singleton: `$this->pdo` is set once at construction (and PDO connections are designed to be reused). `query()` does not accumulate anything on the object.

### Tax calculator

```php
class TaxCalculator
{
    public function __construct(private readonly float $defaultRate) {}

    // Stateless method: same inputs → same output, nothing stored
    public function calculate(float $amount): float
    {
        return round($amount * $this->defaultRate, 2);
    }
}
```

Pure function wrapped in a class. Safe as a singleton.

---

## 6 — Dangerous Singletons: What They Look Like

### Shopping cart (classic mistake)

```php
class ShoppingCart
{
    private array $items = []; // ← mutable, accumulates across calls

    public function add(string $sku, int $qty): void
    {
        $this->items[] = ['sku' => $sku, 'qty' => $qty];
    }

    public function getItems(): array { return $this->items; }
    public function total(): int { return count($this->items); }
}
```

As a singleton: User A's items persist when User B's request arrives. Transient scope fixes this.

### Authentication context

```php
class AuthContext
{
    private ?string $userId = null; // ← set per-request, never auto-reset

    public function authenticate(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserId(): ?string { return $this->userId; }
    public function isAuthenticated(): bool { return $this->userId !== null; }
}
```

As a singleton: the authenticated user from request N is still set for request N+1. Either use transient scope, or redesign as an immutable value object constructed from the request headers.

---

## 7 — The PHP-DI Scope Syntax Reference

```php
use function DI\autowire;
use function DI\create;
use function DI\factory;
use function DI\env;
use function DI\get;

return [
    // ── SINGLETON (default) ────────────────────────────────────────────────

    // Auto-wire: PHP-DI resolves constructor args by type automatically
    LoggerInterface::class => autowire(FileLogger::class),

    // create(): explicit constructor args (for non-type-hinted params)
    DatabaseConnection::class => create(DatabaseConnection::class)
        ->constructor(
            DI\env('DB_DSN'),
            DI\env('DB_USER'),
            DI\env('DB_PASS')
        ),

    // ── NEEDS A FRESH INSTANCE ─────────────────────────────────────────────
    // Note: these are still SHARED when resolved with get(). The factory()
    // is here because the container cannot guess how to build them, not to
    // control lifetime. Call $container->make() where freshness matters.

    ShoppingCartInterface::class => factory(function (): ShoppingCart {
        return new ShoppingCart();
    }),

    // factory() with injected args: PHP-DI resolves them and passes them in
    RequestContextInterface::class => factory(
        function (LoggerInterface $logger): RequestContext {
            return new RequestContext($logger, $_SERVER['REQUEST_URI'] ?? '/');
        }
    ),

    // Transient using a class that implements __invoke
    // (useful when the factory logic is complex enough to deserve its own class)
    ReportBuilderInterface::class => factory(ReportBuilderFactory::class),
];
```

---

## 8 — Quick Reference

```
Scope decision:
  Has mutable state? → TRANSIENT (or redesign as stateless — Lesson 6.4)
  Stateless after construction? → SINGLETON (safe)

PHP-DI syntax:
  Definitions — how to BUILD (never how many):
    autowire(ClassName::class)        — resolve constructor args from type hints
    create(ClassName::class)          — you supply the constructor args
    factory(fn() => new ClassName())  — arbitrary construction logic
    factory(fn(Dep $d) => new C($d))  — with dependencies injected into the callable

  Resolution — how MANY:
    $container->get($id)              — build once, share forever (all definitions)
    $container->make($id)             — build a new one every call

Verifying shared:
  $a = $container->get(Foo::class);
  $b = $container->get(Foo::class);
  assert($a === $b); // same instance

Verifying fresh:
  $a = $container->make(Bar::class);
  $b = $container->make(Bar::class);
  assert($a !== $b); // different instances

Safe singleton checklist:
  ✅ All properties set in constructor, never changed by public methods
  ✅ No array property appended to by public methods
  ✅ No nullable property set by a "set current X" method
  ✅ No bool "initialised" flag toggled after construction
  ✅ Method outputs depend only on method inputs + immutable constructor state
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 3, 4, and 7 are the most important
- [ ] Run `examples/01-singleton-vs-transient.php` — observe the `===` difference
- [ ] Run `examples/02-safe-singletons.php` — understand what makes them safe
- [ ] Run `examples/03-dangerous-singletons.php` — see the scope fix in action
- [ ] Read `challenge/CHALLENGE.md` before opening the starter file
- [ ] Complete `challenge/starter/ScopeAssignmentTest.php`
- [ ] Only open `challenge/solution/ScopeAssignmentTest.php` after all tests pass
- [ ] Complete `quiz/QUIZ.md` cold

---

*Next lesson: **6.3 — The Danger of Stateful Services** — identify the five anti-patterns that cause production lifecycle bugs and write tests that catch each one.*