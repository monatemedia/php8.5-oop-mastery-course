# Lesson 6.4 — Designing Stateless Services
> **Module 6: Object Lifecycle & State Management** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-6.4-designing-stateless-services/
├── README.md                                      ← Theory (you are here)
│
├── examples/
│   ├── 01-making-services-stateless.php           ← Refactoring each anti-pattern from Lesson 6.3
│   ├── 02-request-context-injection.php           ← Per-request data via transient factory
│   └── 03-immutable-value-objects.php             ← When state is correct: readonly + value objects
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── StatelessRefactorTest.php              ← Scaffold — refactor five stateful services
│   └── solution/
│       └── StatelessRefactorTest.php              ← Full solution with commentary
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — Sections 2 through 6 each refactor one anti-pattern.
2. Run each example with `./vendor/bin/phpunit` and read every annotation.
3. Work through the challenge before opening the solution.
4. Take the quiz cold.

---

## 1 — The Stateless Service Rule

A service is **stateless** when its methods' outputs depend only on their inputs — never on accumulated instance state. The same method, called with the same arguments, always returns the same result, regardless of what has been called before.

```
Stateful:   method(args) + instance state → result
Stateless:  method(args) → result
```

Stateless services have three concrete advantages:

1. **Scope-safe by construction.** A stateless singleton cannot contaminate across requests because there is no state to contaminate with.
2. **Trivially testable.** No `setUp()` state, no call ordering, no hidden preconditions. Pass in, assert out.
3. **Composable.** Two stateless services can share an instance without any coordination protocol.

The refactoring technique is always the same: **move state out of the object and into method parameters and return values**. The caller owns and manages the state; the service just transforms it.

---

## 2 — Refactoring Anti-Pattern 1: The Accumulating Service

### Before (stateful)

```php
class ReportService
{
    private array $results = [];

    public function addResult(array $row): void { $this->results[] = $row; }
    public function getResults(): array         { return $this->results; }
}

// Caller:
$service->addResult($row1);
$service->addResult($row2);
$report = $service->getResults();
```

The service owns the array. It grows between calls. As a singleton, it accumulates forever.

### After (stateless)

```php
class ReportService
{
    // No private state. The array lives with the caller.

    public function processRow(array $row): array
    {
        // Validate, enrich, transform — return without storing
        return array_merge($row, ['processed_at' => date('Y-m-d H:i:s')]);
    }

    public function summarise(array $processedRows): array
    {
        return [
            'total'    => count($processedRows),
            'rows'     => $processedRows,
            'generated' => date('Y-m-d H:i:s'),
        ];
    }
}

// Caller owns the accumulation:
$rows = [];
foreach ($rawData as $raw) {
    $rows[] = $service->processRow($raw);
}
$report = $service->summarise($rows);
```

The service is now a pure transformer. The caller accumulates — which is correct, because the caller is the one who knows how many rows there are and when to stop.

**The key move:** every piece of state that was stored on `$this` is now either a method parameter (incoming) or a return value (outgoing).

---

## 3 — Refactoring Anti-Pattern 2: Auth State on a Singleton

### Before (stateful)

```php
class AuthService
{
    private ?User $currentUser = null;

    public function login(User $user): void   { $this->currentUser = $user; }
    public function getUser(): ?User          { return $this->currentUser; }
}
```

### After (stateless via immutable RequestContext)

The insight: "who is the current user?" is a per-request fact. It belongs in an immutable value object constructed from the request — not on a service that is mutated each time.

```php
// Immutable value object — carries per-request identity
final class RequestContext
{
    public function __construct(
        public readonly ?User   $user,
        public readonly string  $requestId,
        public readonly string  $path,
    ) {}

    public static function authenticated(User $user, string $requestId, string $path): self
    {
        return new self(user: $user, requestId: $requestId, path: $path);
    }

    public static function anonymous(string $requestId, string $path): self
    {
        return new self(user: null, requestId: $requestId, path: $path);
    }

    public function isAuthenticated(): bool { return $this->user !== null; }

    public function requireRole(string $role): void
    {
        if (!$this->user?->hasRole($role)) {
            throw new \RuntimeException("Insufficient permissions");
        }
    }
}

// AuthService becomes a factory — stateless
class AuthService
{
    public function __construct(private readonly TokenVerifierInterface $verifier) {}

    // Creates a RequestContext from the incoming request — no state stored
    public function contextFromRequest(ServerRequestInterface $request): RequestContext
    {
        $token = $request->getHeaderLine('Authorization');
        $user  = $token ? $this->verifier->verify($token) : null;

        return $user
            ? RequestContext::authenticated($user, uniqid(), $request->getUri()->getPath())
            : RequestContext::anonymous(uniqid(), $request->getUri()->getPath());
    }
}
```

`RequestContext` is created fresh per request (either by a transient factory or explicitly at the composition root), is immutable, and is injected wherever the current user is needed. `AuthService` has no state — it just reads the request and produces a value.

---

## 4 — Refactoring Anti-Pattern 3: Request-Scoped Data on a Singleton

### Before (stateful)

```php
class RequestLogger
{
    private string $requestId = '';

    public function setRequestId(string $id): void { $this->requestId = $id; }
    public function log(string $msg): void { echo "[{$this->requestId}] {$msg}"; }
}
```

### After (stateless — parameter injection)

The simplest refactor: pass the context as a method parameter.

```php
class RequestLogger
{
    // No state. requestId is passed in with every call.
    public function log(string $requestId, string $msg, string $level = 'info'): void
    {
        echo "[{$requestId}] [{$level}] {$msg}\n";
    }
}
```

If threading `$requestId` through every `log()` call feels tedious, use a partially-applied wrapper:

```php
// At the start of request handling — create a bound logger for this request
class BoundLogger
{
    public function __construct(
        private readonly RequestLogger $logger,
        private readonly string        $requestId,
    ) {}

    // Delegates to the stateless logger with the bound requestId
    public function log(string $msg, string $level = 'info'): void
    {
        $this->logger->log($this->requestId, $msg, $level);
    }
}
```

`RequestLogger` is a stateless singleton. `BoundLogger` is a lightweight transient created per request, binding the stateless logger to the current request ID.

---

## 5 — Refactoring Anti-Pattern 4: Counter / Statistics on a Singleton

### Before (stateful)

```php
class ApiCallTracker
{
    private int $count = 0;

    public function recordCall(): void  { $this->count++; }
    public function getCount(): int     { return $this->count; }
    public function isOverLimit(): bool { return $this->count >= 3; }
}
```

### After — depends on the intent

**Variant A — per-request counter:** the counter is meaningful only within one request. Make it stateless by accepting and returning the count:

```php
class ApiCallTracker
{
    private const LIMIT = 3;

    // No state. Count is owned by the caller.
    public function recordCall(int $currentCount): int
    {
        return $currentCount + 1;
    }

    public function isOverLimit(int $count): bool
    {
        return $count >= self::LIMIT;
    }
}

// Caller owns the count:
$callCount = 0;
$callCount = $tracker->recordCall($callCount); // returns 1
$callCount = $tracker->recordCall($callCount); // returns 2
if ($tracker->isOverLimit($callCount)) { ... }
```

**Variant B — global/durable counter:** the count must survive request boundaries. Move it to an external store:

```php
class ApiCallTracker
{
    public function __construct(private readonly RedisInterface $redis) {}

    public function recordCall(string $key): int
    {
        return $this->redis->incr("api_calls:{$key}");
    }

    public function isOverLimit(string $key, int $limit): bool
    {
        return (int) $this->redis->get("api_calls:{$key}") >= $limit;
    }
}
```

The counter now lives in Redis — durable, shareable across workers, and resettable with a TTL.

---

## 6 — Refactoring Anti-Pattern 5: Deferred Initialisation

### Before (stateful)

```php
class CacheWarmer
{
    private bool $warmed = false;

    public function warm(): void
    {
        if ($this->warmed) return;
        // ... warm
        $this->warmed = true;
    }
}
```

### After — two options

**Option A — Eager construction:** move the warm logic into the constructor. PHP-DI constructs the singleton once; the constructor runs once; warming happens exactly once without a flag.

```php
class CacheWarmer
{
    // No $warmed flag. The constructor IS the warm.
    // PHP-DI calls the constructor once (singleton) — warm happens once.
    public function __construct(private readonly CacheInterface $cache)
    {
        $this->loadIntoCache();
    }

    private function loadIntoCache(): void
    {
        // ... warm the cache
    }

    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }
}
```

**Option B — TTL-based freshness check:** if the warm must expire and refresh, replace the boolean flag with a timestamp and a TTL:

```php
class CacheWarmer
{
    private ?int $lastWarmedAt = null;
    private const TTL_SECONDS  = 300; // re-warm every 5 minutes

    public function warm(): void
    {
        if ($this->lastWarmedAt !== null
            && (time() - $this->lastWarmedAt) < self::TTL_SECONDS) {
            return; // still fresh
        }
        // ... warm
        $this->lastWarmedAt = time();
    }
}
```

This is still stateful (timestamp on `$this`), but the condition is no longer a one-way latch — it expires after `TTL_SECONDS`.

---

## 7 — When Holding State IS Correct: Value Objects

Not all instance state is dangerous. The anti-patterns in Lesson 6.3 involve **services** that accumulate or mutate state between method calls. **Value objects** hold state correctly because:

- They are immutable after construction — no method changes their properties
- They represent a fact at a point in time — `Money(100, 'USD')`, `RequestContext(user, id)`
- They are created fresh per use — a new `Money` is not a contaminated old `Money`

```php
// ✅ Correct use of instance state: immutable value object
final class Money
{
    public function __construct(
        public readonly int    $cents,      // immutable
        public readonly string $currency,   // immutable
    ) {}

    // Returns a NEW Money — does not mutate $this
    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Currency mismatch');
        }
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->cents * $factor), $this->currency);
    }
}
```

`Money` holds state (`$cents`, `$currency`) but it is safe because:
1. The state is set once at construction — never changed
2. Every "mutation" returns a new object — `$this` is unmodified
3. Two consumers holding the same `Money` instance see the same value forever

This is the `readonly` property pattern (PHP 8.1+) applied consistently.

---

## 8 — The Refactoring Decision Tree

```
Does the class have a private property written by a public method?
│
├── YES
│   │
│   ├── Is the state only needed within one request/operation?
│   │   ├── YES → Move to method parameters + return values (stateless refactor)
│   │   │         OR use transient scope
│   │   └── NO  → Move to external store (Redis, DB, session)
│   │             with appropriate TTL / partitioning
│   │
│   └── Is the state set once at construction and never changed?
│       ├── YES → It is a value object. Make properties readonly.
│       │         Safe as singleton.
│       └── NO  → See above branches
│
└── NO → The class is already stateless. Safe as singleton.
```

---

## 9 — Quick Reference

```
The stateless refactoring moves:

  Anti-pattern 1 (Accumulating array)
    Before: $this->results[] = $row;   getResults()
    After:  processRow($row): array    caller collects into their own $rows[]

  Anti-pattern 2 (Auth state)
    Before: $this->currentUser = $user;   getUser()
    After:  RequestContext value object injected per-request (readonly properties)

  Anti-pattern 3 (Request-scoped data)
    Before: $this->requestId = $id;   log($msg)
    After:  log($requestId, $msg)  OR  BoundLogger wraps stateless logger

  Anti-pattern 4 (Counter) — per-request
    Before: $this->count++;   getCount()
    After:  recordCall(int $current): int   isOverLimit(int $count): bool

  Anti-pattern 4 (Counter) — global/durable
    After:  redis->incr($key)   redis->get($key)

  Anti-pattern 5 (Boolean latch)
    Before: if ($this->warmed) return;
    After:  constructor-time init  OR  TTL-based timestamp check

Value objects — hold state correctly:
  - All properties readonly
  - Mutations return NEW objects
  - Fresh instance per use (never reuse a mutated value object)
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 2 through 6 are the core content
- [ ] Run `examples/01-making-services-stateless.php` — see all five refactors in tests
- [ ] Run `examples/02-request-context-injection.php` — understand the RequestContext pattern
- [ ] Run `examples/03-immutable-value-objects.php` — understand when state IS correct
- [ ] Read `challenge/CHALLENGE.md` before opening the starter file
- [ ] Complete `challenge/starter/StatelessRefactorTest.php`
- [ ] Only open `challenge/solution/StatelessRefactorTest.php` after all tests pass
- [ ] Complete `quiz/QUIZ.md` cold

---

*Next lesson: **6.5 — Factory Definitions for Complex Lifecycles** — control object construction explicitly when auto-wiring is not enough.*