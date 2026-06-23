# Lesson 6.3 — The Danger of Stateful Services
> **Module 6: Object Lifecycle & State Management** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-6.3-danger-of-stateful-services/
├── README.md                                      ← Theory (you are here)
│
├── examples/
│   ├── 01-accumulating-service.php                ← Anti-pattern 1 demonstrated and caught
│   ├── 02-auth-state-leak.php                     ← Anti-pattern 2: user A's data leaks to user B
│   └── 03-all-five-antipatterns.php               ← All five, each with the test that exposes the bug
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── StatefulServiceAuditTest.php           ← Scaffold — identify, classify, and catch five bugs
│   └── solution/
│       └── StatefulServiceAuditTest.php           ← Full solution with commentary
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — Sections 2 through 6 each cover one anti-pattern in depth.
2. Run each example with `./vendor/bin/phpunit` and read every annotation.
3. Work through the challenge before opening the solution.
4. Take the quiz cold.

---

## 1 — Why Anti-Patterns, Not Just Rules

Lessons 6.1 and 6.2 gave you the concepts (share-nothing, singleton vs transient) and the decision rule (mutable state → transient). This lesson gives you the **taxonomy** — five distinct shapes that stateful-singleton bugs take in real codebases.

Knowing the taxonomy matters because:
- Code review becomes faster: you recognise the pattern immediately rather than reasoning from first principles each time
- The fix for each anti-pattern is different: some need transient scope, some need external storage, some need redesign
- The tests that catch each pattern are structurally distinct — knowing the pattern tells you what test to write

Each anti-pattern has a canonical form (the property shape that identifies it), a canonical bug (what goes wrong in production), and a canonical test (the assertion that catches it before production).

---

## 2 — Anti-Pattern 1: The Accumulating Service

### Shape

```php
class ReportService
{
    private array $results = [];          // ← starts empty

    public function addResult(array $row): void
    {
        $this->results[] = $row;          // ← appends every call
    }

    public function getResults(): array
    {
        return $this->results;            // ← reads accumulation
    }
}
```

**Identifying markers:** a `private array` (or `private Collection`) property initialised to `[]` that is appended to by a public method.

### What goes wrong

As a singleton in a persistent worker, `$results` accumulates every call from every request since the worker started. Request 1 adds 3 rows, request 2 adds 5 rows — by request N the array has hundreds of rows. `getResults()` returns all of them indiscriminately.

### The test that catches it

```php
$service = new ReportService(); // one instance — simulates singleton

// Operation 1
$service->addResult(['user' => 'Alice', 'score' => 99]);

// Operation 2 (same instance — no reset)
$service->addResult(['user' => 'Bob', 'score' => 77]);

// Bug: operation 2 sees operation 1's data
$this->assertCount(2, $service->getResults()); // should be 1 if isolated
```

### The fix

Transient scope (each consumer gets a fresh empty array), or redesign the service to be stateless (accept and return the array through method parameters — covered in Lesson 6.4).

---

## 3 — Anti-Pattern 2: Authentication State on a Singleton

### Shape

```php
class AuthService
{
    private ?User $currentUser = null;    // ← set per-request

    public function login(User $user): void
    {
        $this->currentUser = $user;       // ← overwrites every call
    }

    public function getUser(): ?User
    {
        return $this->currentUser;        // ← reads "current" state
    }
}
```

**Identifying markers:** a `private ?SomeType $current` property (or `$activeUser`, `$authenticatedUser`) set by a `login()`, `setUser()`, or `authenticate()` method.

### What goes wrong

User A's identity is set on the singleton during request 1. Request 2 arrives from an unauthenticated user or a completely different user — but the singleton still reports User A as the current user. Any code that calls `getUser()` before the new user's `login()` executes receives User A's identity.

In a multi-tenant SaaS, this is a data breach: tenant A's data is served to tenant B's request.

### The test that catches it

```php
$service = new AuthService();

// Request 1: User A logs in
$service->login(new User('alice', 'admin'));
$this->assertSame('alice', $service->getUser()->getName());

// Request 2: no login — should be unauthenticated
// Bug: Alice is still logged in
$this->assertNull($service->getUser()); // FAILS — returns Alice
```

### The fix

Transient scope, or redesign using an immutable `RequestContext` value object constructed from the request headers/session at the start of each request and injected as a dependency (covered in Lesson 6.4).

---

## 4 — Anti-Pattern 3: Request-Scoped Data on a Singleton

### Shape

```php
class RequestLogger
{
    private string $requestId = '';       // ← set per-request

    public function setRequestId(string $id): void
    {
        $this->requestId = $id;           // ← overwritten each request
    }

    public function log(string $msg): void
    {
        echo "[{$this->requestId}] {$msg}";  // ← reads per-request state
    }
}
```

**Identifying markers:** a `private string` (or `private int`, `private array`) property with a `set*()` method that is expected to be called once at the start of each request/job to configure the service for that operation.

### What goes wrong

Subtler than the auth bug: `setRequestId()` IS called for every request. The problem appears when two concurrent coroutines (under Swoole or FrankenPHP) interleave: coroutine A sets request ID "req-001", then coroutine B sets it to "req-002" before coroutine A finishes logging. All of coroutine A's subsequent log lines carry "req-002" — wrong attribution.

Even without concurrency, the problem appears if `setRequestId()` is ever called late (after some logging has already happened) or not called at all for a particular code path.

### The test that catches it

```php
$logger = new RequestLogger();

// Request 1 sets its ID and logs
$logger->setRequestId('req-001');
$logger->log('Processing started');

// Request 2 sets ITS ID (simulates a second call in same worker)
$logger->setRequestId('req-002');

// Bug: if request 1 still holds a reference to $logger
// and logs again, it now logs with req-002's ID
$logger->log('Result stored'); // logs [req-002] — should be [req-001]
```

### The fix

Transient scope, or pass the request ID as a parameter to `log(string $requestId, string $msg)` rather than storing it as state (covered in Lesson 6.4).

---

## 5 — Anti-Pattern 4: Counter / Statistics on a Singleton

### Shape

```php
class RequestCounter
{
    private int $count = 0;              // ← starts at zero

    public function increment(): void
    {
        $this->count++;                  // ← grows with every call
    }

    public function get(): int
    {
        return $this->count;             // ← reads accumulated total
    }
}
```

**Identifying markers:** a `private int` (or `private float`) property initialised to `0` and incremented by a public method.

### What goes wrong

This anti-pattern has two variants:

**Variant A — Unintended accumulation:** the counter was intended to count something within one request (e.g. how many times a rate-limited API was called in this request). As a singleton it counts globally across all requests since worker startup — the limit is hit immediately on the second request.

**Variant B — Misleading statistics:** the counter was intended to track a global total (e.g. requests processed). As a singleton in a worker that handles 10,000 requests before recycling, the count is meaningful — but if the worker restarts, the count resets to zero. Statistics that look like totals are actually "since last restart" — misleading, and only correct by accident.

### The test that catches it

```php
$counter = new RequestCounter();

// Request 1: 2 API calls — counter reads 2 (correct)
$counter->increment();
$counter->increment();
$this->assertSame(2, $counter->get());

// Request 2: 1 API call — counter SHOULD read 1
$counter->increment(); // counter now reads 3

// Bug: request 2 is already "over limit" from request 1's history
$this->assertSame(1, $counter->get()); // FAILS — returns 3
```

### The fix

Transient scope if the count is per-request, or push to an external store (Redis INCR, database column) with appropriate TTL/partitioning if the count is meant to be global and durable (covered in Lesson 6.4).

---

## 6 — Anti-Pattern 5: Deferred Initialisation That Never Resets

### Shape

```php
class CacheWarmer
{
    private bool $warmed = false;         // ← toggled once

    public function warm(): void
    {
        if ($this->warmed) return;        // ← early exit forever
        // ... expensive warm operation
        $this->warmed = true;
    }

    public function isWarmed(): bool
    {
        return $this->warmed;
    }
}
```

**Identifying markers:** a `private bool $initialised = false` (or `$booted`, `$warmed`, `$ready`) flag that is set to `true` by a one-time setup method and checked with a guard clause that prevents re-execution.

### What goes wrong

In a persistent worker, the cache is warmed at worker startup — which is correct. The problem arises when the underlying data changes: a deployment updates the product catalogue, an admin flushes the cache, or a background job invalidates data. The `CacheWarmer` singleton never re-warms because `$warmed = true` persists indefinitely. Every request sees stale cached data until the worker is restarted.

### The test that catches it

```php
$warmer = new CacheWarmer(/* ... */);

$warmer->warm(); // first call: warms correctly
$this->assertTrue($warmer->isWarmed());

// Simulate cache invalidation — the warmer should re-warm on next call
// But it won't — $warmed is still true
$warmer->invalidate(); // or: the data source changes externally

$warmer->warm(); // should re-warm — but the guard clause skips it
$this->assertTrue($warmer->isWarmedWithFreshData()); // FAILS — stale
```

### The fix

Use PHP-DI's lazy proxy or factory pattern to defer construction until first use, but allow reconstruction when invalidated. Or redesign the warming logic to check data freshness rather than a boolean flag (covered in Lesson 6.5).

---

## 7 — How to Spot Stateful Services in Code Review

Scan every service class for these signatures. Any match requires examination:

| Signal | Pattern | Anti-pattern |
|--------|---------|-------------|
| Array appender | `private array $x = []` + public `add*()`/`append*()` | Accumulating service (#1) |
| Nullable setter | `private ?Type $x = null` + public `set*()`/`login()`/`authenticate()` | Auth state (#2) |
| String/int context setter | `private string $x = ''` + public `set*()`/`configure()` | Request context (#3) |
| Incrementing counter | `private int $x = 0` + public `increment()`/`count()` | Counter/stats (#4) |
| Boolean flag | `private bool $x = false` + public `init()`/`warm()`/`boot()` | Deferred init (#5) |

The scan takes 30 seconds per class. Running it during code review catches lifecycle bugs before they reach production.

---

## 8 — Quick Reference

```
The five anti-patterns:

  #1 Accumulating service
     private array $results = []  +  addResult()
     Bug: results from previous operations contaminate current operation
     Test: same instance, two operations, assertCount too high on second

  #2 Auth state on singleton
     private ?User $currentUser = null  +  login()
     Bug: previous user's identity is present at start of next request
     Test: login() on instance, then assertNull(getUser()) on same instance

  #3 Request-scoped data on singleton
     private string $requestId = ''  +  setRequestId()
     Bug: concurrent operations share the same "current" context
     Test: set context, set different context, verify first context is gone

  #4 Counter/statistics on singleton
     private int $count = 0  +  increment()
     Bug: per-request counter accumulates across all requests
     Test: increment twice, new "request", increment once, assertSame(1, get())

  #5 Deferred initialisation
     private bool $warmed = false  +  warm()
     Bug: warm() never re-executes after first call, even when data is stale
     Test: warm(), invalidate data, warm() again, assertFalse(isStillFresh())

Scan trigger in code review:
  Any private property + public setter/appender/incrementer = examine scope
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 2 through 6 are the core content
- [ ] Run `examples/01-accumulating-service.php` — read every comment and test
- [ ] Run `examples/02-auth-state-leak.php` — understand the privilege-escalation risk
- [ ] Run `examples/03-all-five-antipatterns.php` — see all five patterns side by side
- [ ] Read `challenge/CHALLENGE.md` before opening the starter file
- [ ] Complete `challenge/starter/StatefulServiceAuditTest.php`
- [ ] Only open `challenge/solution/StatefulServiceAuditTest.php` after all tests pass
- [ ] Complete `quiz/QUIZ.md` cold

---

*Next lesson: **6.4 — Designing Stateless Services** — refactor all five anti-patterns into stateless equivalents that are safe in any scope.*