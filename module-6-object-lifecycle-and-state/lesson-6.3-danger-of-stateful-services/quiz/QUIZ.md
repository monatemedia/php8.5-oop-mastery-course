# Quiz — Lesson 6.3: The Danger of Stateful Services
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** Which property signature is the canonical marker for Anti-Pattern 1 (Accumulating Service)?

- A) `private bool $processed = false`
- B) `private array $results = []` with a public `addResult()` method
- C) `private ?User $current = null` with a public `login()` method
- D) `private int $count = 0` with a public `decrement()` method

---

**Q2.** A `CartService` singleton has `private array $items = []`. Request 1 adds 3 items; request 2 adds 2 items. What does `count($cartService->getItems())` return at the end of request 2?

- A) 2 — only request 2's items
- B) 3 — only request 1's items (request 2 overwrote them)
- C) 5 — both requests' items accumulated
- D) 0 — PHP resets array properties between method calls

---

**Q3.** An `AuthService` has `private ?User $currentUser = null` set by `login()`. Request 1 authenticates Alice (admin). Request 2 is unauthenticated — `login()` is never called. What does `$authService->getUser()` return during request 2?

- A) `null` — PHP-DI resets nullable properties between requests
- B) `null` — the singleton's `logout()` is called automatically at request end
- C) Alice's `User` object — the property persists from request 1
- D) A new, anonymous `User` object — PHP creates a default instance

---

**Q4.** Which of the following is the **most serious real-world consequence** of Anti-Pattern 2 (Auth State on Singleton) in a multi-tenant SaaS application?

- A) Slightly higher memory usage per worker process
- B) Users occasionally see a "loading" state
- C) One tenant's private data is served to another tenant's request
- D) Authentication tokens expire faster than expected

---

**Q5.** A `RequestLogger` singleton has `private string $requestId = ''` set by `setRequestId()`. In a Swoole coroutine application, coroutine A sets `requestId = 'req-001'` and then yields. Coroutine B sets `requestId = 'req-002'`. Coroutine A resumes and calls `log('done')`. What correlation ID appears in the log line?

- A) `req-001` — coroutine A's local copy is preserved
- B) `req-002` — B's `setRequestId()` overwrote the singleton's property
- C) Both, interleaved — PHP logs both IDs simultaneously
- D) An empty string — coroutine yields reset string properties

---

**Q6.** A `PageViewCounter` singleton has `private int $count = 0` incremented by `recordView()`. It was intended to enforce a per-request limit of 10 page views per API consumer. After the third API request (each making 4 page views), what is `getCount()`, and what problem does this cause?

- A) 4 — resets per request; no problem
- B) 12 — accumulated across all three requests; the limit was hit after the third call on request 1 and every subsequent request is immediately over-limit
- C) 10 — the counter caps at the limit automatically
- D) 0 — the counter is reset by the garbage collector between requests

---

**Q7.** A `CacheWarmer` singleton has `private bool $warmed = false`. Its `warm()` method begins with `if ($this->warmed) return;`. The cache is warmed at worker startup. Three hours later, a deployment updates the underlying data. What happens when `warm()` is called again?

- A) It re-warms the cache — the `if` check has a time-based expiry
- B) It returns immediately without re-warming — `$this->warmed` is still `true`
- C) It throws a `CacheAlreadyWarmException`
- D) It triggers a garbage collection cycle that resets all boolean properties

---

**Q8.** You are auditing a service class. You find these properties: `private array $errors = []` and `private bool $validated = false`. Which anti-patterns are present?

- A) Anti-pattern 1 only — only the array matters
- B) Anti-pattern 5 only — only the boolean matters
- C) Anti-pattern 4 and Anti-pattern 2
- D) Anti-pattern 1 and Anti-pattern 5 — the array accumulates and the boolean is a one-way latch

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | The `reset()` / `clear()` method fix for Anti-pattern 1 is adequate when the reset is called in a `finally` block. | |
| 10 | Anti-pattern 3 (Request-scoped data) is dangerous even in synchronous PHP-FPM if `setContext()` is ever called late or skipped in a code path. | |
| 11 | A service with `private int $errorCount = 0` incremented by `recordError()` is always using Anti-pattern 4 (Counter/statistics). | |
| 12 | The test for any stateful-singleton bug should create TWO service instances — one for "request 1" and one for "request 2". | |
| 13 | Anti-pattern 5 (Deferred initialisation) is only a problem if the underlying data source changes after the first boot. | |
| 14 | A `NotificationQueue` that is flushed at the end of every request cannot exhibit Anti-pattern 1 — the flush prevents accumulation. | |

---

## Section C — Short Answer

**Q15.** Explain why Anti-pattern 2 (Auth State on Singleton) is considered a security vulnerability rather than just a data consistency bug. What category of security incident does it enable?

*Your answer:*

---

**Q16.** A developer argues: "Anti-pattern 5 is not a bug — the deferred initialisation is working correctly. `warm()` is supposed to run only once." Explain why the developer is correct in a share-nothing context and wrong in a persistent-worker context.

*Your answer:*

---

**Q17.** You are asked to write a test that proves a service exhibits Anti-pattern 4 (Counter/statistics on singleton). Describe the three-act structure of that test in concrete terms, including what value you would assert at the end to confirm the bug.

*Your answer:*

---

## Section D — Code Reading

**Q18.** Read this service class. Name the anti-pattern(s) present, write the simplest test that would expose each bug, and state the appropriate fix for each.

```php
class JobProcessor
{
    private array   $processedIds  = [];
    private ?string $currentTenant = null;
    private bool    $warmedUp      = false;

    public function warmUp(): void
    {
        if ($this->warmedUp) return;
        // ... expensive initialisation
        $this->warmedUp = true;
    }

    public function setTenant(string $tenantId): void
    {
        $this->currentTenant = $tenantId;
    }

    public function process(string $jobId): void
    {
        $this->processedIds[] = $jobId;
    }

    public function getProcessedIds(): array  { return $this->processedIds; }
    public function getCurrentTenant(): ?string { return $this->currentTenant; }
    public function isWarmedUp(): bool        { return $this->warmedUp; }
}
```

*Your answer:*

---

**Q19.** This test is claimed to prove that `AuthService` does NOT have Anti-pattern 2. Explain why the test does NOT prove this claim, and write the correct test.

```php
public function testAuthServiceDoesNotLeakIdentity(): void
{
    $auth1 = new AuthService();
    $auth1->login(new User('alice', 'admin'));
    $this->assertSame('alice', $auth1->getUser()->getName());

    $auth2 = new AuthService(); // fresh instance
    $this->assertNull($auth2->getUser());
}
```

*Your answer:*

---

**Q20.** Trace through what happens to `$notificationQueue` over three requests in a persistent worker, given this code:

```php
// Bootstrap (runs once at worker startup):
$queue = new NotificationQueue(); // singleton

// Request 1: order confirmation
$queue->enqueue('email', ['to' => 'alice@example.com']);
$queue->enqueue('sms',   ['to' => '+44-7700-000001']);
$sent1 = $queue->flush();

// Request 2: payment failure (throws before flush)
$queue->enqueue('email', ['to' => 'bob@example.com', 'subject' => 'Payment failed']);
// Exception thrown — flush() never called

// Request 3: subscription renewal
$queue->enqueue('email', ['to' => 'charlie@example.com', 'subject' => 'Subscription renewed']);
$sent3 = $queue->flush();
```

Answer: (a) What is in `$sent1`? (b) What is in `$sent3`? (c) What notification was never sent, and what notification was sent late and out of context?

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
| 1 | **B** | `private array $results = []` with a public append method is the canonical marker for Anti-pattern 1. A is Anti-pattern 5 (boolean latch). C is Anti-pattern 2 (auth state). D is Anti-pattern 4 (counter) — though `decrement()` is unusual. |
| 2 | **C** | The singleton's `$items` array is never reset between requests. Request 1 appends 3; request 2 appends 2 to the same array — total 5. PHP does not reset array properties between method calls; the array persists on the object for its entire lifetime. |
| 3 | **C** | `$currentUser` is a property on the singleton object. It was set to Alice during request 1. Nothing resets it before request 2. `login()` was never called for request 2, so the property remains pointing at Alice. PHP-DI does not reset singleton properties between requests. |
| 4 | **C** | In a multi-tenant SaaS, Auth-state leakage means tenant A's identity (and therefore their `tenantId`) is present when tenant B's request reads `getUser()`. Any code that uses the tenant ID to scope database queries will return tenant A's data to tenant B — a data breach, a GDPR incident, and a loss of data isolation guarantees. |
| 5 | **B** | In Swoole coroutines, all coroutines share the same PHP process and the same object heap. `setRequestId()` writes to `$this->requestId` on the singleton. Coroutine B's write overwrites coroutine A's value. When coroutine A resumes and calls `log()`, it reads the current value — `req-002`. There is no "local copy" per coroutine for shared object properties. |
| 6 | **B** | After three requests of 4 views each: `getCount()` = 12. The per-request limit of 10 was hit mid-request-1 (at the 10th view). Every call after the 10th is over-limit. Requests 2 and 3 start at counts 4 and 8 respectively — request 2 hits the limit on its 3rd view, request 3 is over-limit before it makes a single view. |
| 7 | **B** | `$this->warmed` is `true` — the guard clause executes `return` immediately. The three-hours-later deployment has no way to reset `$this->warmed` on the singleton object. From the singleton's perspective, it is still "warm" — it has no awareness that the underlying data changed. |
| 8 | **D** | `private array $errors = []` with an `addError()` or similar append method is Anti-pattern 1. `private bool $validated = false` that is set to `true` by a `validate()` method and never reset is Anti-pattern 5 (one-way latch — once validated, always validated). Both can be present in the same class. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | `finally` is more robust than remembering to call `reset()` manually, but it is still inadequate. First, it still requires every callsite to use try/finally. Second, a collaborator that holds a reference to the service and calls it between the finally block and the next try might see stale state. Third, the reset() method itself represents test-infrastructure inside production code (Rule 1 violation). The correct fix eliminates the need for reset() entirely. |
| 10 | **T** | Under PHP-FPM, share-nothing protects between requests but not within a request. If `setContext()` is called late (after a logging call) or skipped (a code path that does not go through the middleware that calls `setContext()`), the log lines before `setContext()` will carry an empty string or the previous request's context. The bug is timing-dependent, not runtime-model-dependent. |
| 11 | **F** | A `private int $errorCount = 0` incremented by `recordError()` might legitimately be Anti-pattern 4 (per-request counter on a singleton) — or it might be correct if the service is transient-scoped and the count is only meaningful within one operation. The anti-pattern requires the COMBINATION of mutable state AND singleton scope. The property alone is a warning sign, not a confirmed anti-pattern. |
| 12 | **F** | The test should use ONE instance for both "requests". Creating two instances proves nothing about singleton contamination — a fresh object always starts clean. The whole point of the test is to simulate the persistent worker's reuse of the same instance. Two instances would be the "share-nothing" comparison test, not the bug-proving test. |
| 13 | **F** | Anti-pattern 5 is also a problem if the `warm()` or `init()` method fails partway through on the first call and `$warmed` is set to `true` before the failure is propagated. The latch flips to `true`, but the object is in a partial state — subsequent calls skip re-initialisation and operate on incomplete data. The bug is the one-way latch itself, not just the change-after-boot scenario. |
| 14 | **F** | A `NotificationQueue` that flushes at the end of every request is safe IF flush() is always called and always completes successfully. If an unhandled exception terminates request 1 before `flush()` runs, the pending notifications remain in the singleton's array. Request 2 then either flushes them itself (sending them late, out of context, to the wrong recipients) or adds to them, amplifying the problem. The protection is conditional — not unconditional. |

## Section C

**Q15 — Model answer:**
Anti-pattern 2 is a security vulnerability because it enables **unauthorised data access** and **privilege escalation**. When the authenticated user's identity leaks from request N to request N+1, the receiving request inherits the previous user's permissions and tenant scope. Concretely: if Alice is an admin in tenant Acme, her `AuthService` state gives the next request (which may be from an anonymous probe, a different user, or a different tenant) admin-level access to Acme's data. The receiving party does not need to supply valid credentials — they receive them implicitly. In a multi-tenant SaaS system, this is a Category A data breach: tenant-A's confidential data (financial records, user lists, configurations) is readable by tenant-B's unauthenticated request. Under GDPR and SOC 2, this is a notifiable incident.

**Q16 — Model answer:**
In a share-nothing (FPM) context, the developer is correct. The `CacheWarmer` object is created at the start of each request and destroyed at the end. `warm()` is called once per request — exactly as intended. The `$warmed` flag is always `false` at the start of every request, so the guard clause never prevents a warm. The deferred-init design works perfectly.

In a persistent-worker context, the developer is wrong. The `CacheWarmer` object is created once at worker startup and lives for the entire worker lifetime. `warm()` is called once at startup — correct. But the flag then stays `true` indefinitely. If the underlying cache data is invalidated (a deployment, a cache flush, an admin action), the `warm()` call that should trigger re-warming is silently ignored by the guard clause. The worker continues serving stale data with no error, no warning, and no indication that anything is wrong. The bug is invisible in development (which uses FPM) and only manifests in production (which uses the persistent runtime).

**Q17 — Model answer:**
The three-act structure for an Anti-pattern 4 test:

**Act 1 (establish state):** Create ONE service instance and increment the counter enough times to establish a non-zero count. Assert the count is correct for this "operation 1."
```php
$counter = new PageViewCounter(limit: 3);
$counter->increment();
$counter->increment();
assertSame(2, $counter->get()); // operation 1: 2 increments
```

**Act 2 (new operation, same instance):** Without resetting, increment once more — simulating "operation 2's first increment."
```php
$counter->increment(); // operation 2's first increment
```

**Act 3 (assert contamination):** Assert that the count is the accumulated total (3), not 1 (what operation 2's count should be). Also assert the over-limit state is triggered after only one increment in operation 2.
```php
assertSame(3, $counter->get(),
    'BUG: counter shows 3 — 2 from operation 1 + 1 from operation 2');
assertTrue($counter->isOverLimit(),
    'BUG: operation 2 is over-limit after only 1 increment of its own');
```

## Section D

**Q18 — Answer:**
`JobProcessor` has three anti-patterns simultaneously:

**Anti-pattern 1 (Accumulating service):** `private array $processedIds = []` appended by `process()`. As a singleton, IDs from every job ever processed accumulate. `getProcessedIds()` returns the entire history, not just the current job's IDs.

Test: create one instance, call `process('job-001')`, then call `process('job-002')`. Assert `count($processor->getProcessedIds()) === 2` (should be 1 for job 2 alone). Fix: transient scope.

**Anti-pattern 3 (Request-scoped data):** `private ?string $currentTenant = null` set by `setTenant()`. If `setTenant()` is skipped or called late for a job, `getCurrentTenant()` returns the previous job's tenant.

Test: call `setTenant('tenant-A')`, then call `setTenant('tenant-B')`, then check that calling `getCurrentTenant()` before `setTenant()` on a "new job" returns the previous tenant. Fix: transient scope (or pass tenantId as a parameter to `process()`).

**Anti-pattern 5 (Deferred init):** `private bool $warmedUp = false` with a `warmUp()` guard clause. Once warmed, `warmUp()` is a no-op forever. If the initialisation data changes, the stale warm state persists.

Test: call `warmUp()`, assert `isWarmedUp() === true`, call `warmUp()` again, assert that the re-initialisation was skipped (the guard prevented it). Fix: stateless redesign (accept init data as a constructor argument) or PHP-DI lazy proxy.

**Q19 — Answer:**
The test does NOT prove the claim because it creates **two separate instances** (`$auth1` and `$auth2`). A fresh `AuthService` instance always has `$currentUser = null` — that is the default value, not proof that contamination cannot occur. The test is checking the trivially obvious: that a new object starts in its initial state.

The correct test proves that the SAME instance — simulating singleton reuse across requests — leaks identity:

```php
public function testAuthServiceLeaksIdentityAsSingleton(): void
{
    $auth = new AuthService(); // ONE instance — simulates singleton

    // Request 1: Alice logs in
    $auth->login(new User('alice', 'admin'));
    $this->assertSame('alice', $auth->getUser()->getName());

    // Request 2: no login call — should be unauthenticated
    // BUG: Alice is still the current user
    $this->assertSame('alice', $auth->getUser()->getName(),
        'BUG: Request 2 sees Alice — identity leaked from request 1'
    );
    $this->assertTrue($auth->isAuthenticated(),
        'BUG: isAuthenticated() is true for an unauthenticated request'
    );
}
```

This test will pass (confirming the bug exists) because the same instance is used and `logout()` was never called.

**Q20 — Answer:**

**(a) What is in `$sent1`?**
`$sent1` contains 2 notifications: Alice's email and the SMS to `+44-7700-000001`. `flush()` was called correctly for request 1 — it returns and clears `$pending`. This is correct behaviour.

**(b) What is in `$sent3`?**
`$sent3` contains 2 notifications: Bob's "Payment failed" email AND Charlie's "Subscription renewed" email. Request 2 enqueued Bob's notification but threw before `flush()`. `$queue->pending` now contains Bob's notification. Request 3 enqueues Charlie's notification and then calls `flush()` — which returns both.

**(c) What was never sent, and what was sent late?**
- Bob's "Payment failed" email **should have been sent during request 2** (promptly, while the payment failure was fresh). It was instead sent during request 3 — potentially hours later, out of sequence with the payment failure event.
- Charlie's notification was sent correctly — it was generated and flushed in the same request.
- Nothing was permanently lost (flush() eventually sent everything), but Bob's notification arrived late and in the wrong context. In a real system: Bob receives a "Payment failed" email long after the event, possibly after he has already resolved the issue — causing confusion and eroding trust. If request 2 had retried (common in job queues), Bob might have received the notification twice.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | All five anti-patterns internalised. Ready for Lesson 6.4 (Designing Stateless Services). |
| 14–17 | Re-read README Sections 2–6 and re-run Example 03 before moving on. |
| Below 14 | Complete the challenge solution review and re-read the canonical markers in README Section 7 before retaking. |