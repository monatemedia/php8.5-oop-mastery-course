# Quiz — Lesson 6.2: Transient vs Singleton Scopes in PHP-DI
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** What is the default scope when you register a service using `autowire(ClassName::class)` in PHP-DI?

- A) Transient — a new instance is created every time the container resolves the binding.
- B) Singleton — one instance is created per container lifetime and reused.
- C) Prototype — a new instance is created per dependency tree.
- D) Request — a new instance is created per HTTP request.

---

**Q2.** Which PHP-DI definition syntax produces a **transient** (new instance per resolution)?

- A) `autowire(ShoppingCart::class)`
- B) `create(ShoppingCart::class)`
- C) `factory(fn() => new ShoppingCart())`
- D) `get(ShoppingCart::class)`

---

**Q3.** You call `$container->get(ShoppingCart::class)` twice and assign the results to `$a` and `$b`. Which assertion confirms the service is registered as a **singleton**?

- A) `$this->assertNotSame($a, $b)`
- B) `$this->assertEquals($a, $b)`
- C) `$this->assertSame($a, $b)`
- D) `$this->assertInstanceOf(ShoppingCart::class, $a)`

---

**Q4.** An `AuditLogger` class has `private array $entries = []` that is appended to by a `log()` method. What scope should it be registered with, and why?

- A) Singleton — `$entries` starts empty, so contamination is impossible.
- B) Singleton — PHP-DI resets private properties between requests automatically.
- C) Transient — `log()` writes to a private property; sharing the instance across consumers or requests would cause entries to accumulate across their boundaries.
- D) Transient — PHP arrays are passed by value, so appending does not affect the original.

---

**Q5.** A `TaxCalculator` has `private readonly float $rate` set in the constructor and a `calculate(float $amount): float` method. No other properties or public methods exist. What scope is correct?

- A) Transient — the `calculate()` method modifies the input amount.
- B) Transient — `readonly` properties can cause unexpected behaviour when shared.
- C) Singleton — `$rate` is immutable after construction and `calculate()` writes nothing to `$this`.
- D) Singleton — only if the rate is the same for all consumers.

---

**Q6.** An `EventDispatcher` takes a `private array $listeners` in its constructor. There is no `addListener()` or `removeListener()` method. Which scope is correct?

- A) Transient — because it takes an array in the constructor.
- B) Transient — because multiple consumers may need different listeners.
- C) Singleton — `$listeners` is set at construction and no public method ever writes to it; `dispatch()` reads but never mutates it.
- D) Singleton only if fewer than 10 listeners are registered.

---

**Q7.** You switch a `ShoppingCart` registration from `autowire(ShoppingCart::class)` to `factory(fn() => new ShoppingCart())`. Which statement correctly describes the effect?

- A) The class code changes — `factory()` compiles the class differently.
- B) The class code is identical; only the container's behaviour changes — it now creates a fresh `ShoppingCart` on every `$container->get()` call instead of reusing one.
- C) The change has no effect unless `ShoppingCart` implements a specific PHP-DI interface.
- D) PHP-DI calls `ShoppingCart::reset()` automatically between resolutions when using `factory()`.

---

**Q8.** You are registering a `PasswordHasher` that takes a `$cost` integer in its constructor. The cost never changes after construction, and `hash()` / `verify()` do not write to `$this`. Which PHP-DI definition is correct?

- A) `factory(fn() => new PasswordHasher(12))` — must be transient because of the constructor argument.
- B) `autowire(PasswordHasher::class)` — but only if `$cost` has a default value.
- C) `create(PasswordHasher::class)->constructor(12)` — singleton with an explicit constructor argument.
- D) Both B and C are correct for singleton; A is also correct (though wasteful) because stateless transients are safe but inefficient.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | Calling `$container->get(Foo::class)` twice with singleton scope invokes the `Foo` constructor twice. | |
| 10 | A class with `private readonly string $name` set in the constructor is safe as a singleton because `readonly` prevents any method from reassigning the property. | |
| 11 | Using transient scope for a stateless service is technically incorrect — it will cause a runtime error. | |
| 12 | A `JobContext` that stores the current job's tenant ID via `setTenant()` is dangerous as a singleton because `setTenant()` writes to a property that `getTenant()` reads. | |
| 13 | Switching a service from singleton to transient scope always requires modifying the service class itself. | |
| 14 | A PHP-DI `factory()` callable is invoked once per container lifetime, just like a singleton, but returns a clone of the instance each time. | |

---

## Section C — Short Answer

**Q15.** Explain the scope decision rule in your own words. What is the single property of a class that determines whether singleton scope is safe?

*Your answer:*

---

**Q16.** A developer argues: "I'll register `OrderBuilder` as a singleton and just call `$builder->reset()` between orders. That way we avoid the overhead of creating a new object for every order." Give two reasons why this is a worse design than using transient scope.

*Your answer:*

---

**Q17.** Two services are both registered as singletons: `FileLogger` and `ShoppingCart`. An HTTP request comes in, calls `$cart->add('WIDGET', 1, 9.99)`, then the response is sent. The next request calls `$cart->getItems()`. What does it see, and why? Then describe the same scenario if `ShoppingCart` were registered as transient.

*Your answer:*

---

## Section D — Code Reading

**Q18.** Read this PHP-DI definitions file. For each registration, state whether it is singleton or transient, and identify which (if any) are incorrectly scoped. Justify each answer.

```php
return [
    LoggerInterface::class        => autowire(FileLogger::class),
    DatabaseConnection::class     => create(DatabaseConnection::class)
                                        ->constructor(DI\env('DB_DSN')),
    ShoppingCartInterface::class  => autowire(ShoppingCart::class),
    AuthContextInterface::class   => autowire(AuthContext::class),
    TaxCalculator::class          => factory(fn() => new TaxCalculator(0.20)),
    ReportBuilderInterface::class => factory(fn() => new ReportBuilder()),
];
```

Assume:
- `FileLogger` has `private readonly string $path` set in the constructor; `log()` writes to disk.
- `DatabaseConnection` has `private readonly \PDO $pdo`; `query()` delegates to `$pdo`.
- `ShoppingCart` has `private array $items = []` appended to by `add()`.
- `AuthContext` has `private ?string $userId = null` set by `authenticate()`.
- `TaxCalculator` has `private readonly float $rate`; `calculate()` is a pure function.
- `ReportBuilder` has `private array $rows = []` appended to by `addRow()`.

*Your answer:*

---

**Q19.** This test passes. What scope is the container using for `RequestLog`, and how do you know? What would need to change in the container registration to make it fail?

```php
public function testRequestLogIsolation(): void
{
    $logA = $this->container->get(RequestLog::class);
    $logA->record('Event from consumer A');

    $logB = $this->container->get(RequestLog::class);

    $this->assertSame(0, $logB->count());
    $this->assertNotSame($logA, $logB);
}
```

*Your answer:*

---

**Q20.** A colleague proposes this "smart singleton" as an alternative to transient scope for `ShoppingCart`:

```php
class ShoppingCart
{
    private array $items = [];
    private static int $instanceCount = 0;

    public function __construct()
    {
        self::$instanceCount++;
    }

    public function add(string $sku): void { $this->items[] = $sku; }
    public function getItems(): array { return $this->items; }

    // New method: returns a fresh cart, "solving" the singleton problem
    public function fresh(): static
    {
        return new static();
    }
}

// Registration (singleton)
ShoppingCartInterface::class => autowire(ShoppingCart::class),

// Usage in code:
$freshCart = $container->get(ShoppingCartInterface::class)->fresh();
$freshCart->add('WIDGET');
```

Identify two problems with this approach compared to simply using transient scope.

*Your answer:*

---

---

# ✅ Answer Key
*(Scroll only after completing all questions)*

&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;

---

## Section A

| Q | Answer | Explanation |
|---|--------|-------------|
| 1 | **B** | PHP-DI's default scope is singleton — the first resolution creates the instance; all subsequent resolutions return the same object. This applies to auto-wiring, `autowire()`, and `create()`. |
| 2 | **C** | `factory(fn() => new ShoppingCart())` produces a transient binding — the callable is invoked on every `get()` call. `autowire()` (A) and `create()` (B) both produce singletons. `get()` (D) is a reference to another binding, not a new definition. |
| 3 | **C** | `assertSame($a, $b)` checks strict object identity (`===`). Two variables pointing to the same singleton instance pass this check. `assertEquals` (B) checks equality, not identity — two different objects with the same data would pass it. |
| 4 | **C** | `log()` writes to `$this->entries`, a private property. When the instance is shared as a singleton, entries from consumer A's log calls are present when consumer B reads `getEntries()`. Transient scope ensures each consumer gets a fresh `$entries = []`. |
| 5 | **C** | `$rate` is `readonly` — it cannot be changed after construction. `calculate()` takes an amount and returns a computed value; it writes nothing to `$this`. Every call to `calculate(100.0)` returns the same result regardless of previous calls. This is the definition of a safe singleton. |
| 6 | **C** | `$listeners` is assigned in the constructor and no public method ever modifies it. `dispatch()` iterates over `$listeners` but never appends, removes, or replaces it. The dispatcher is effectively immutable after construction — safe singleton. Answer B might seem appealing but misunderstands the pattern: if different consumers need different listeners, they should receive different dispatcher instances constructed with different listeners — that is a constructor argument question, not a scope question. |
| 7 | **B** | The scope change happens entirely in the container definition. The `ShoppingCart` class code is unchanged. PHP-DI's `factory()` means "call this callable every time `get()` is asked for this binding." The class has no knowledge of its scope. |
| 8 | **C** | `create(PasswordHasher::class)->constructor(12)` is the correct explicit singleton with a non-type-hinted constructor argument. D is also technically correct but `TaxCalculator(0.20)` wrapped in `factory()` is wasteful — creating a new stateless object on every resolution wastes memory and time for no benefit. The cleanest answer for a stateless class is C (singleton with explicit arg). |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | Singleton scope invokes the constructor exactly ONCE. Every subsequent `get()` returns the instance created on the first call — the constructor is not invoked again. |
| 10 | **T** | `readonly` prevents any code — including other methods in the same class — from reassigning the property after construction. If ALL properties are `readonly` and set in the constructor, the class is immutable by PHP's enforcement, not just by convention. |
| 11 | **F** | Using transient scope for a stateless service is perfectly valid and causes no errors. It is wasteful (unnecessary object construction on every resolution) but not incorrect. The scope decision rule is about safety, not about prohibition of the other choice. |
| 12 | **T** | `setTenant()` writes `$this->tenantId`; `getTenant()` reads it. When the `JobContext` singleton is shared between job 1 and job 2, `setTenant()` from job 1 sets the value that job 2's `getTenant()` reads — until job 2 calls `setTenant()` itself. This is exactly the context-leakage anti-pattern. |
| 13 | **F** | Switching from `autowire(ClassName::class)` to `factory(fn() => new ClassName())` is a change to the container DEFINITION file only. The service class itself is identical. This is the key lesson: scope is a wiring decision, not a class design decision. |
| 14 | **F** | A `factory()` callable is invoked on EVERY `get()` call — not once. It does not return a clone; it returns a new object from scratch each time. Cloning is a separate PHP operation (`clone`) not involved in PHP-DI's factory mechanism. |

## Section C

**Q15 — Model answer:**
A class is safe as a singleton if and only if no public method writes to a property that another public method reads — except for properties set at construction time and never changed afterwards. Put another way: after the constructor finishes, the object's observable state must be fixed. If a method can change the object's state (`log()` appends to `$entries`, `authenticate()` sets `$userId`, `addItem()` appends to `$items`), the object holds mutable state and must be transient (or redesigned to be stateless).

**Q16 — Model answer:**
Two reasons the `reset()` approach is inferior to transient scope:

First, the contract is invisible and unenforceable. There is nothing in the type system, PHP-DI's API, or the class's interface that reminds or forces callers to call `reset()` before each use. A developer who writes new code that uses the `OrderBuilder`, or a code path that is only exercised under certain conditions, may never call `reset()`. The bug returns silently. Transient scope enforces freshness at the container level — no caller can forget it because the container handles it.

Second, it leaks internal state management into consuming code. Every consumer of `OrderBuilder` now needs to know that the service has internal state that must be cleared before use. This breaks encapsulation. A well-designed service should present a clean interface regardless of its internal history. Requiring callers to manage lifecycle is the same problem as the `clear()` approach critiqued in the Lesson 6.1 quiz.

**Q17 — Model answer:**
With `ShoppingCart` as a singleton: the next request calls `$cart->getItems()` and sees `[['sku' => 'WIDGET', 'qty' => 1, 'price' => 9.99]]` — the item added by the first request. The singleton instance was never garbage collected. The `$items` array persists on it across requests. Any request to the same worker gets the same `ShoppingCart` object with the accumulated items.

With `ShoppingCart` as transient: `$container->get(ShoppingCartInterface::class)` in the second request calls `new ShoppingCart()` — a completely fresh object with `$items = []`. The second request sees an empty array and has no knowledge of what the first request added. The two requests are isolated.

## Section D

**Q18 — Answer:**

| Registration | Scope | Correct? |
|---|---|---|
| `LoggerInterface` → `autowire(FileLogger::class)` | Singleton | ✅ Correct — `$path` is readonly, `log()` writes to disk not to `$this` |
| `DatabaseConnection` → `create(...)->constructor(...)` | Singleton | ✅ Correct — `$pdo` is readonly, `query()` delegates to `$pdo` without mutating `$this` |
| `ShoppingCartInterface` → `autowire(ShoppingCart::class)` | Singleton | ❌ **Wrong** — `ShoppingCart` has `private array $items` appended by `add()`; as a singleton, items accumulate across consumers/requests |
| `AuthContextInterface` → `autowire(AuthContext::class)` | Singleton | ❌ **Wrong** — `AuthContext` has `private ?string $userId` set by `authenticate()`; as a singleton, the user identity from request N is present in request N+1 |
| `TaxCalculator` → `factory(fn() => new TaxCalculator(0.20))` | Transient | ⚠️ Technically safe but wasteful — `TaxCalculator` is stateless (pure computation), so transient works but creates unnecessary objects. Singleton would be more efficient. Not wrong, just suboptimal. |
| `ReportBuilderInterface` → `factory(fn() => new ReportBuilder())` | Transient | ✅ Correct — `ReportBuilder` has `private array $rows` appended by `addRow()`; transient ensures each report starts with empty rows |

**Q19 — Answer:**
The container is using **transient** scope. The evidence is in the two assertions:

`$this->assertSame(0, $logB->count())` — if the container were singleton, `$logB` would be the same object as `$logA`, and `$logB->count()` would return `1` (because `$logA->record(...)` already incremented the count). The test asserts `0`, which is only possible if `$logB` is a fresh object that has never had `record()` called on it.

`$this->assertNotSame($logA, $logB)` — explicitly confirms different object instances.

To make the test **fail**, change the container registration from transient to singleton:
```php
// Change this:
$this->container->transient(RequestLog::class, fn() => new RequestLog());
// To this:
$this->container->singleton(RequestLog::class, fn() => new RequestLog());
```
With singleton scope, `$logB` would be the same object as `$logA`, so `$logB->count()` would return `1`, failing `assertSame(0, ...)`.

**Q20 — Answer:**
Two problems with the `fresh()` approach:

First, it defeats dependency injection. The consuming code calls `$container->get(...)->fresh()` — meaning it must know that the singleton cart is "dirty" and needs to be freshened. This leaks the knowledge of scope (and the hack to work around it) into the calling code. With transient scope, `$container->get(ShoppingCartInterface::class)` always returns a fresh cart — callers do not need to know anything about the internal state of what they receive. The container encapsulates the lifecycle decision; `fresh()` moves it back into consumer code.

Second, `fresh()` is invisible at the type boundary. `ShoppingCartInterface` (the interface) does not have a `fresh()` method — it is on the concrete class. Consumers that depend on `ShoppingCartInterface` (which they should, per the DIP) cannot call `fresh()` without downcasting. And if `fresh()` is added to the interface, every implementation must now provide it — an interface method whose sole purpose is working around a scope mistake in the container definition. The correct fix (transient registration) requires no interface changes, no new methods, and no consumer awareness.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Scope decisions fully internalised. Ready for Lesson 6.3 (The Danger of Stateful Services). |
| 14–17 | Re-read README Sections 4 and 7, then re-examine Example 03 and the challenge solution before moving on. |
| Below 14 | Revisit all three examples, re-read the scope decision rule, and redo the challenge before retaking. |