# Quiz — Lesson 6.4: Designing Stateless Services
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** What is the defining characteristic of a stateless service?

- A) It has no constructor.
- B) Its methods' outputs depend only on their inputs — never on accumulated instance state.
- C) It has no public methods.
- D) It is registered as transient in PHP-DI.

---

**Q2.** You are refactoring an accumulating service. The original has `private array $results = []` appended by `addResult()`. After the stateless refactor, where does the `$results` array live?

- A) In a Redis cache, keyed by the caller's ID.
- B) In the caller's own local variable; the service transforms individual rows and returns them.
- C) In a `static` property on the service class.
- D) In PHP-DI's container, stored as a singleton binding.

---

**Q3.** The stateless refactor of `recordCall(): void` (Anti-pattern 4) produces which signature?

- A) `recordCall(): void` — unchanged, but now thread-safe.
- B) `recordCall(): int` — returns the new count without accepting the old one.
- C) `recordCall(int $currentCount): int` — accepts the current count and returns the incremented value.
- D) `static recordCall(int &$count): void` — modifies a reference.

---

**Q4.** An `AuthService` singleton currently stores the current user via `login(User $user)`. After the stateless refactor, how is the current user conveyed to services that need it?

- A) Via a global variable `$_CURRENT_USER` set at request start.
- B) Via an immutable `RequestContext` value object created at the start of each request and injected as a method parameter.
- C) Via a `static ?User $current` property on the `User` class.
- D) Via PHP's session — `$_SESSION['user']`.

---

**Q5.** What is the correct PHP 8.5 syntax for creating a copy of a readonly object with one property changed?

- A) `$new = $original; $new->status = 'shipped';`
- B) `$new = new static(...$original, status: 'shipped');`
- C) `$new = clone $original with { status: 'shipped' };`
- D) `$new = $original->with(status: 'shipped');`

---

**Q6.** A `Money` value object has `public readonly int $cents` and `public readonly string $currency`. Its `add(Money $other): self` method creates a new `Money` with the sum. Which statement is correct?

- A) `Money` is dangerous as a singleton because `$cents` is mutable.
- B) `Money` is a value object: its properties are set once at construction, never changed after; `add()` returns a new object rather than mutating `$this`.
- C) `Money` should be refactored to be stateless by making `$cents` a method parameter.
- D) `Money` needs transient scope in PHP-DI to avoid contamination.

---

**Q7.** A `FeatureFlagService` had `private bool $booted = false` and a `boot()` method with a guard clause. After the stateless refactor, what is the correct mechanism ensuring flags are loaded exactly once (for a singleton)?

- A) The guard clause is kept but moved to `isEnabled()`.
- B) A `static bool $booted` property replaces the instance property.
- C) The flags are loaded eagerly in the constructor — PHP-DI calls the constructor once for a singleton, so loading happens exactly once without any flag.
- D) The flags are loaded in a `__destruct()` method that fires before each request.

---

**Q8.** A `BoundLogger` wraps a stateless `OperationLogger` singleton, binding a `$correlationId` at construction time. Which statement correctly describes this pattern?

- A) `BoundLogger` is a stateful singleton — it accumulates log entries.
- B) `BoundLogger` is a transient: created fresh per request with the current `correlationId`, it delegates to the stateless singleton; the singleton never stores the ID.
- C) `BoundLogger` replaces the `OperationLogger` singleton — only one of them should exist in the container.
- D) `BoundLogger` is unnecessary — the `correlationId` should be stored in a PHP session.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | After the stateless refactor of `NotificationQueue`, `flush()` still returns the pending list — but it is now the caller's responsibility to reset their `$pending` variable after calling `flush()`. | |
| 10 | A value object with all `readonly` properties is equivalent to a stateless service — neither can cause lifecycle bugs when used as a singleton. | |
| 11 | The `BoundLogger` pattern (a transient wrapper around a stateless singleton) is an example of Anti-pattern 3 because it stores a `correlationId` as instance state. | |
| 12 | After refactoring `CurrentOperationContext` to an immutable value object, two concurrent operations can safely share the same `CurrentOperationContext` instance. | |
| 13 | The stateless refactor of `ApiCallTracker` (passing and returning the count) means the caller must check `isOverLimit()` using their own `$count` variable rather than relying on the tracker to maintain the count internally. | |
| 14 | The `clone with` syntax in PHP 8.5 modifies the original object's properties in-place and returns a reference to it. | |

---

## Section C — Short Answer

**Q15.** Explain the phrase "the caller owns the state." In the context of the stateless `NotificationQueue`, what does the caller own, how do they own it, and why is this safer than the service owning it?

*Your answer:*

---

**Q16.** What is the difference between an immutable value object and a stateless service? Give one example of each and explain why each cannot cause a singleton lifecycle bug.

*Your answer:*

---

**Q17.** A developer refactors `AuthService` by adding `static ?User $currentUser = null` (a static property instead of instance property) to "fix" the singleton problem. Explain why this is not a fix and arguably makes the bug worse.

*Your answer:*

---

## Section D — Code Reading

**Q18.** The following stateful service needs to be refactored to stateless. Write the complete stateless version, showing only the changed method signatures and the removed property.

```php
// BEFORE (stateful)
class PageViewTracker
{
    private array $pages   = [];
    private int   $bounces = 0;

    public function trackView(string $page): void
    {
        $this->pages[] = $page;
    }

    public function trackBounce(): void
    {
        $this->bounces++;
    }

    public function getPages(): array  { return $this->pages; }
    public function getBounces(): int  { return $this->bounces; }
    public function getBounceRate(): float
    {
        if (empty($this->pages)) return 0.0;
        return round($this->bounces / count($this->pages) * 100, 1);
    }
}
```

*Your answer (write the stateless version):*

---

**Q19.** This `RequestContext` is being passed by a service to a downstream collaborator. What potential issue does this introduce, and what property of `RequestContext` prevents it from being exploited?

```php
class OrderService
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function placeOrder(RequestContext $ctx, array $items): array
    {
        $ctx->requireAuthentication();
        // ... build the order ...
        return $this->payments->charge($ctx, $total); // passing ctx to collaborator
    }
}

class PaymentService
{
    public function charge(RequestContext $ctx, Money $amount): array
    {
        $ctx->requireAuthentication();
        return ['userId' => $ctx->user->id, 'charged' => $amount->amount()];
    }
}
```

*Your answer:*

---

**Q20.** Read this refactored service. Identify one remaining design problem and explain how to fix it.

```php
class InvoiceBuilder
{
    private string $currency = 'USD'; // ← set at construction, not changed after

    public function __construct(string $currency = 'USD')
    {
        $this->currency = $currency;
    }

    public function addLine(array $lines, string $sku, int $qty, float $price): array
    {
        $lines[] = ['sku' => $sku, 'qty' => $qty, 'price' => $price, 'currency' => $this->currency];
        return $lines;
    }

    public function total(array $lines): float
    {
        return array_sum(array_map(fn($l) => $l['price'] * $l['qty'], $lines));
    }

    public function setCurrency(string $currency): void // ← this method
    {
        $this->currency = $currency;
    }
}
```

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
| 1 | **B** | The stateless service rule (README Section 1): output depends only on inputs, never on accumulated instance state. A is wrong — stateless services have constructors. D is wrong — scope is a container decision, not a class property. |
| 2 | **B** | The key move for Anti-pattern 1: the caller accumulates in their own variable. `$rows = []; $rows[] = $service->processRow($raw);`. The service transforms one item and returns it; accumulation is the caller's concern. |
| 3 | **C** | `recordCall(int $currentCount): int` — pass in the current count, get back the new count. The caller passes their local `$count` and stores the return value. This is the "pass-and-return" pattern that eliminates the `$count` property from the class. |
| 4 | **B** | An immutable `RequestContext` value object is constructed at the start of each request (by a factory at the composition root) and injected into services as a method parameter. The `AuthService` becomes a stateless factory that produces the context; services that need identity receive the context object directly. |
| 5 | **C** | PHP 8.5 `clone with` syntax: `$new = clone $original with { propertyName: newValue };`. This produces a new object where all properties are copied from `$original` except those listed in the `with` block. A is invalid (readonly cannot be reassigned). B is not PHP syntax. D does not exist. |
| 6 | **B** | `Money` is a value object: `$cents` and `$currency` are set in the constructor (`readonly`) and never changed. `add()` returns `new self(...)` — it never calls `$this->cents = ...`. Value objects hold state correctly — they are not "stateful" in the dangerous sense, because their state is immutable. |
| 7 | **C** | The constructor loads flags eagerly — `$this->flags = $configSource;` — with no guard clause. For a PHP-DI singleton, the constructor is called exactly once. No flag is needed because "runs only once" is enforced structurally (by the object lifecycle) rather than by a runtime check. |
| 8 | **B** | `BoundLogger` is transient: created fresh per request, it holds the current `correlationId` as a readonly constructor argument and delegates to the singleton `OperationLogger`. The singleton never stores the ID — it is passed as a parameter. This is the correct pattern: transient wrapper + stateless singleton. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | After the stateless refactor, `flush(array $pending): array` simply returns the array. The "clearing" is done by the caller: `$pending = []` after `$sent = $queue->flush($pending)`. The queue has nothing to reset — it never held the array. |
| 10 | **F** | A value object with all `readonly` properties is safe as a singleton because it cannot be mutated. A stateless service is safe because it holds no mutable state. They are equivalent in that both are lifecycle-safe. However, value objects are typically not used as singletons — they are created fresh for each value they represent. The statement conflates lifecycle safety with usage pattern. |
| 11 | **F** | `BoundLogger` stores `$correlationId` as a readonly constructor argument — not as a mutating property. The `correlationId` is the intended per-request context, set immutably at construction. This is NOT Anti-pattern 3. Anti-pattern 3 is a mutable property set by `setCorrelationId()` after construction. The distinction: immutable constructor state is safe (value object pattern); mutable post-construction state is the anti-pattern. |
| 12 | **F** | After refactoring to an immutable value object, each operation creates its OWN `CurrentOperationContext` with its own name and timestamp. They should NOT share the same instance — sharing would be incorrect (operation A's context should not be operation B's context). The point of the refactor is that the objects are independent by design, not shareable. |
| 13 | **T** | With the stateless refactor, the caller owns the count: `$count = 0; $count = $tracker->recordCall($count);`. `isOverLimit($count)` takes the caller's count as a parameter. The tracker has no internal count — it is a rules engine, not a state container. |
| 14 | **F** | `clone with` produces a NEW object. The original is completely unchanged. The new object has all the original's property values except those explicitly overridden in the `with` block. This is copy-on-write semantics, not in-place mutation. |

## Section C

**Q15 — Model answer:**
"The caller owns the state" means the pending notifications array lives as a local variable in the code that is running the request — not inside the service. Concretely, the caller writes:

```php
$pending = [];
$pending = $queue->enqueue($pending, 'email', [...]);
$sent    = $queue->flush($pending);
$pending = [];
```

The caller owns `$pending` in three senses: (1) they declare and initialise it; (2) they pass it to the service as an input and receive the updated version as an output; (3) they are responsible for resetting it after flush. This is safer because the variable's lifetime is tied to the calling scope — when the request ends, `$pending` goes out of scope and is freed. There is no singleton object to carry stale notifications into the next request. If the caller forgets to flush (exception, early return), the notifications simply disappear with the scope — they cannot accumulate in a shared object and unexpectedly appear in a future request.

**Q16 — Model answer:**
An **immutable value object** holds state but cannot change it after construction. Example: `Money(int $cents, string $currency)` with `readonly` properties. "Mutation" methods (`add()`, `multiply()`) return new `Money` objects — `$this` is never changed. It cannot cause a singleton lifecycle bug because there is no way for one consumer's use to change the object's state that another consumer sees.

A **stateless service** holds no instance state beyond what was set in the constructor and is never changed by public methods. Example: `TaxCalculator(float $rate)` where `calculate(float $amount)` returns `$amount * $rate` and writes nothing to `$this`. It cannot cause a singleton lifecycle bug because every call to `calculate()` reads only `$rate` (immutable) and `$amount` (parameter) — there is no accumulated history.

The key difference: a value object IS data (it represents a value, like `$100 USD`). A stateless service IS behaviour (it transforms inputs, like "apply 20% tax"). Both are safe as singletons for the same structural reason: neither can accumulate mutable per-call state.

**Q17 — Model answer:**
A `static` property is shared across ALL instances of the class AND persists for the entire PHP process lifetime. This makes the bug significantly worse than an instance property on a singleton:

With an instance property on a singleton, the bug is scoped to the specific container's lifetime. If you have two containers (e.g. in tests), they have separate singleton instances and separate `$currentUser` values.

With a `static` property, `AuthService::$currentUser` is shared across ALL instances everywhere in the process. You cannot escape it by creating a fresh `AuthService` instance — the `static` property persists regardless. Tests that create `new AuthService()` thinking they get a clean slate are wrong: the static property from a previous test is still set. Static state is global state — it is the worst possible form of the anti-pattern because it is invisible (not in the instance) and universal (affects every instance everywhere).

## Section D

**Q18 — Model answer:**

```php
// AFTER (stateless)
class PageViewTracker
{
    // No $pages or $bounces properties.

    public function trackView(array $pages, string $page): array
    {
        $pages[] = $page;
        return $pages;
    }

    public function trackBounce(int $bounces): int
    {
        return $bounces + 1;
    }

    // These become pure computations — take the data as parameters
    public function getBounceRate(array $pages, int $bounces): float
    {
        if (empty($pages)) return 0.0;
        return round($bounces / count($pages) * 100, 1);
    }
}

// Caller:
$pages   = [];
$bounces = 0;
$pages   = $tracker->trackView($pages, '/home');
$pages   = $tracker->trackView($pages, '/about');
$bounces = $tracker->trackBounce($bounces);
$rate    = $tracker->getBounceRate($pages, $bounces);
```

The `$pages` array and `$bounces` counter move from the service to the caller's local variables. The service becomes a rules engine: it applies transformations to data passed in and returns the updated data.

**Q19 — Answer:**
The potential concern: `RequestContext` is passed to `PaymentService`, which could theoretically use it for purposes beyond authentication (e.g. reading `ctx->user->id` to log or validate). If `RequestContext` were mutable, the payment service could accidentally or maliciously modify the user identity seen by later code in `OrderService`.

The property that prevents this: `RequestContext` has all `readonly` properties and is declared `final`. `readonly` prevents any code — including `PaymentService` — from reassigning properties on the context object after construction. `final` prevents subclasses from overriding this guarantee. The context passed to `PaymentService` is guaranteed to be identical when control returns to `OrderService`. There is no way for `PaymentService` to corrupt the request identity.

This is the critical invariant of value objects used as context carriers: immutability makes them safe to pass deeply through a call stack without a "defensive copy" at each layer.

**Q20 — Answer:**
The remaining design problem is `setCurrency(string $currency): void`. Despite the constructor correctly setting `$this->currency`, the `setCurrency()` method allows external code to change it after construction. This re-introduces a mutable property that could be exploited as a context-setter anti-pattern: caller A sets currency to 'GBP', calls `addLine()`, caller B sets currency to 'EUR', and suddenly caller A's in-progress invoice lines have a different currency than intended.

The fix is to remove `setCurrency()` entirely and make `$currency` a `readonly` property:

```php
class InvoiceBuilder
{
    public function __construct(
        private readonly string $currency = 'USD',
    ) {}

    public function addLine(array $lines, string $sku, int $qty, float $price): array { ... }
    public function total(array $lines): float { ... }
    // setCurrency() removed — currency is immutable after construction
}
```

If different currencies are needed, the caller creates a new `InvoiceBuilder` with the appropriate currency: `new InvoiceBuilder('GBP')`. This enforces the single-currency-per-builder constraint structurally rather than relying on callers not calling `setCurrency()`.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Stateless design fully internalised. Ready for Lesson 6.5 (Factory Definitions). |
| 14–17 | Re-read README Sections 2–5 and the challenge solution commentary before moving on. |
| Below 14 | Redo the challenge, read each refactored service's WHY comment, then retake. |