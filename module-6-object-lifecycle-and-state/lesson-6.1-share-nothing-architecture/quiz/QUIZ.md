# Quiz — Lesson 6.1: PHP's Share-Nothing Architecture
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** Under a standard PHP-FPM setup, what happens to an object created during request 1 by the time request 2 arrives?

- A) It is kept in a process-level cache and reused if the same class is requested again.
- B) The worker's memory is freed at the end of the request, so the object no longer exists.
- C) It survives only if it was registered as a singleton in a DI container.
- D) It survives until the PHP garbage collector runs, which may be several requests later.

---

**Q2.** Which of these is **not** one of the three object lifecycles described in this lesson?

- A) Request lifecycle — the object lives for one HTTP request.
- B) Worker lifecycle — the object lives until the worker process dies.
- C) Script lifecycle — the object lives until the CLI script exits.
- D) Session lifecycle — the object lives until the user's session cookie expires.

---

**Q3.** A `UserSessionService` stores the authenticated user in `private ?string $userId`. The application runs under PHP-FPM today. What is the accurate statement about its safety?

- A) It is permanently safe — PHP-FPM's share-nothing model guarantees the property is cleared between requests.
- B) It is unsafe today, because PHP-FPM reuses worker processes and worker reuse preserves object state.
- C) It is safe between requests under FPM, but the safety comes from the runtime rather than from the class, so it breaks the moment the app moves to a persistent worker.
- D) It is unsafe only if the class is registered as a transient in the container.

---

**Q4.** Which pair of constants would you check to detect that you are running inside a persistent worker runtime?

- A) `PHP_SAPI` and `PHP_VERSION`
- B) `SWOOLE_VERSION` and `FRANKENPHP_VERSION`
- C) `PHP_WORKER_MODE` and `PHP_PERSISTENT`
- D) `APP_ENV` and `APP_DEBUG`

---

**Q5.** A batch importer runs as a single CLI invocation and processes 50,000 CSV rows through one `RowImporter` instance that appends every validation error to `private array $errors`. The script is run once a night and exits cleanly. Is there a lifecycle problem?

- A) No — the script exits when it finishes, so share-nothing is preserved.
- B) No — CLI scripts are exempt from lifecycle concerns because they do not serve HTTP requests.
- C) Yes — the same instance spans all 50,000 rows, so `$errors` grows without bound and row 50,000 is processed by an object in a very different state from row 1.
- D) Yes — but only because CSV parsing is memory-intensive; the `$errors` array itself is irrelevant.

---

**Q6.** Your team runs PHP-FPM and has no plans to adopt Swoole or FrankenPHP. Which of the following is still a real risk from stateful singleton services?

- A) None — share-nothing makes stateful singletons completely safe under FPM.
- B) Two services that both receive the same singleton instance within a single request can see each other's mutations.
- C) The singleton will be serialised into the session and restored on the next request.
- D) PHP will raise a warning at shutdown if a singleton still holds mutable state.

---

**Q7.** What does the phrase "share-nothing is an accidental safety feature" mean in the context of this lesson?

- A) PHP deliberately isolates requests to protect badly written code, and this is documented as a design goal.
- B) The isolation is a side effect of the process model, not something the code earned — badly written stateful code appears correct only because the process dies before the bug can surface.
- C) It means PHP shares nothing with other languages, so techniques from Java or Python do not apply.
- D) It means the safety is unreliable, and even under FPM state can randomly leak between requests.

---

**Q8.** Which statement best describes what the migration from PHP-FPM to FrankenPHP worker mode changes about your application code?

- A) The code must be rewritten to use coroutines and async I/O throughout.
- B) The code does not change, but the lifetime of every object created outside the request handler changes from one request to the whole worker lifetime — so latent state bugs become live ones.
- C) All services must be re-registered as transient; there is no other change.
- D) Nothing changes; FrankenPHP emulates share-nothing per request.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | Under PHP-FPM, a `static` property retains its value from one HTTP request to the next in the same worker process. | |
| 10 | A queue worker running `php artisan queue:work` handles many jobs inside a single PHP process, so a stateful service can leak state from job to job. | |
| 11 | `php_sapi_name()` returning `'cli'` proves the process is short-lived. | |
| 12 | You need Swoole or FrankenPHP before stateful singletons can cause any observable bug. | |
| 13 | Designing a service to be stateless costs more effort than designing it stateful, which is why it is deferred until a persistent runtime is actually adopted. | |
| 14 | The most reliable way to know whether your code is running in a persistent worker is an explicit deployment contract, such as an `APP_WORKER_MODE` environment variable, rather than runtime sniffing. | |

---

## Section C — Short Answer

**Q15.** In your own words, explain why a bug in a stateful service can sit undetected in a PHP-FPM codebase for years and then appear on the first day of a FrankenPHP deployment. What exactly changed?

*Your answer:*

---

**Q16.** A developer says: "Our test suite passes, so our services must be lifecycle-safe." Explain why a passing test suite is weak evidence for lifecycle safety, and describe what a test would have to do differently to be real evidence.

*Your answer:*

---

**Q17.** You are asked to detect at runtime whether the application is in a persistent worker. List three signals you could check and state what each one does and does not tell you. Then explain why an explicit deployment flag is preferable to all three.

*Your answer:*

---

## Section D — Code Reading

**Q18.** This service was written for PHP-FPM. The company is migrating to FrankenPHP worker mode. Identify every property that becomes dangerous, state precisely what a second request would observe, and say which property (if any) is safe.

```php
final class CheckoutService
{
    private array $lineItems = [];
    private ?string $couponCode = null;
    private int $callCount = 0;
    private readonly TaxCalculator $tax;

    public function __construct(TaxCalculator $tax)
    {
        $this->tax = $tax;
    }

    public function addItem(string $sku, float $price): void
    {
        $this->lineItems[] = ['sku' => $sku, 'price' => $price];
        $this->callCount++;
    }

    public function applyCoupon(string $code): void
    {
        $this->couponCode = $code;
    }

    public function total(): float
    {
        $subtotal = array_sum(array_column($this->lineItems, 'price'));
        if ($this->couponCode === 'SAVE10') {
            $subtotal *= 0.9;
        }
        return $this->tax->withTax($subtotal);
    }
}
```

Assume `TaxCalculator` has a single `private readonly float $rate` and a pure `withTax()` method.

*Your answer:*

---

**Q19.** This test is offered as proof that `AuditLogger` is lifecycle-safe. Explain why it proves nothing, then rewrite it so that it would actually fail against the buggy implementation.

```php
public function testAuditLoggerRecordsEntries(): void
{
    $logger = new AuditLogger();
    $logger->record('user.login');
    $logger->record('order.created');

    $this->assertCount(2, $logger->entries());
}

// The implementation under test:
final class AuditLogger
{
    private array $entries = [];

    public function record(string $event): void { $this->entries[] = $event; }
    public function entries(): array { return $this->entries; }
}
```

*Your answer:*

---

**Q20.** A colleague proposes this fix for the leaking `UserSessionService`, arguing it makes the class safe for worker mode without changing the container registration:

```php
final class UserSessionService
{
    private ?string $userId = null;

    public function authenticate(string $userId): void
    {
        $this->userId = $userId;
    }

    public function currentUser(): ?string
    {
        return $this->userId;
    }

    // New: call this at the start of every request to wipe the leak
    public function clear(): void
    {
        $this->userId = null;
    }
}

// In the request handler:
$container->get(UserSessionService::class)->clear();
```

Give two reasons why this is a weaker fix than making the service stateless or transient. Then describe one realistic scenario in which this code leaks a user identity anyway.

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
| 1 | **B** | At the end of a request the worker's entire memory is freed — every object, every variable, every accumulated array. The next request starts from a blank slate. This is the whole of share-nothing in one sentence. A is wrong: PHP has no cross-request object cache. C confuses container scope (which lives inside one request under FPM) with process lifetime. D is wrong: the GC is irrelevant because the memory goes away wholesale at shutdown. |
| 2 | **D** | There is no "session lifecycle" for objects. A session is data serialised to a store and rehydrated on the next request — it is not an object that stays alive in memory. Confusing the two is a common source of the belief that `$_SESSION` and a stateful singleton are the same mechanism. They are not. |
| 3 | **C** | This is the central point of the lesson. The class is unsafe by design — it holds mutable state that one method writes and another reads. Under FPM it *behaves* correctly, but the correctness is supplied by the process dying, not by the class. Move it to a worker runtime and nothing about the class changed, yet it now leaks user identities. B is wrong on a detail worth being precise about: FPM does reuse worker processes, but it destroys all userland state between requests, so object state does not survive. |
| 4 | **B** | `SWOOLE_VERSION` is defined when the Swoole extension is loaded; `FRANKENPHP_VERSION` when the FrankenPHP SAPI is active. A is a red herring — `PHP_SAPI` tells you the interface, not the lifetime. C names constants that do not exist. D is application config, unrelated to runtime. |
| 5 | **C** | The script lifecycle is the third case, and it is the one people forget because there is no HTTP request to anchor the mental model. One process, one object, 50,000 rows: `$errors` accumulates for the entire run. Memory grows, and any logic that reads `$errors` — a "did this row fail?" check, an error-count threshold — sees every earlier row's failures. A is the exact misconception the lesson targets: exiting cleanly at the end does not help rows 2 through 50,000. |
| 6 | **B** | Share-nothing protects you **between** requests. It does nothing **within** one. If `ReportService` is a singleton and both `DashboardService` and `ExportService` receive the same instance, a mutation made through one is visible through the other. This is the point in Section 4 that surprises experienced developers. C invents a mechanism PHP does not have; D invents a warning PHP does not emit. |
| 7 | **B** | "Accidental" refers to where the safety comes from. The isolation is a consequence of the process model, and it applies equally to well-written and badly-written code — which means it hides the difference between them. Code that only works because the process dies has not been proven correct; it has been protected from having to be. A misreads "accidental" as "deliberate". D overstates it: under FPM the isolation is genuinely reliable — that is precisely why the bugs stay hidden. |
| 8 | **B** | The migration is a runtime change, not a code change — and that is exactly what makes it dangerous. Objects created outside the request handler (a bootstrapped container, its singletons) go from living for one request to living for the worker's whole life. No line of application code was edited, so nothing in code review flags it, yet the lifetime of every one of those objects has changed. A describes a different (and optional) concern; C is part of the fix but not the change itself; D is false — worker mode is the opposite of per-request isolation. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | Static properties are userland state and are destroyed with everything else at request shutdown. The next request re-initialises them. This is worth being exact about because "statics persist" is true *within* a request and across a worker's lifetime under Swoole, but false across requests under FPM. |
| 10 | **T** | This is Model 2 in Section 3, and it is the most common way a team meets this bug without ever adopting Swoole. `queue:work` pops jobs in a `while` loop inside one process. A service that stores job-specific state carries it into the next job. |
| 11 | **F** | `'cli'` tells you the interface, not the lifetime. A one-line script and a queue worker that runs for three weeks both report `'cli'`. The CLI SAPI is in fact where the *longest*-lived processes are found. |
| 12 | **F** | Two counter-examples from this lesson alone: a stateful singleton shared between collaborators leaks within a single FPM request (Section 4), and a long-running CLI script leaks between iterations (Model 3). Neither involves a persistent HTTP runtime. |
| 13 | **F** | The claim inverts the actual cost. Stateless services — dependencies injected, no hidden mutable state, results returned rather than accumulated — are the same services that are easy to test (Module 5) and well-designed (Modules 1–4). You are not paying a lifecycle-safety tax; you are getting lifecycle safety for free from design you wanted anyway. Deferring it means retrofitting under deployment pressure. |
| 14 | **T** | Runtime sniffing is a collection of heuristics, each with blind spots: a constant may not be defined yet during bootstrap, `php_sapi_name()` cannot distinguish a short script from a daemon, and a new runtime arrives with a new signal you do not check for. An explicit contract — the deployment sets `APP_WORKER_MODE=persistent` — is unambiguous, greppable, and testable in both states. |

## Section C

**Q15 — Model answer:**
Nothing about the class changed. What changed is how long its instances live.

Under PHP-FPM the object's lifetime is bounded by the request. A service that writes state on one call and reads it on a later call *does* have the bug — but the only calls that can ever observe it belong to the same request, where the state is usually the state that request itself just set. The process then dies and takes the contamination with it. The bug is real and continuously present; it simply has no window in which to be observed.

Under FrankenPHP worker mode the object is created once at worker bootstrap and lives until the worker is recycled — thousands of requests later. The write from request 1 is now visible to the read in request 2. The window opened.

The uncomfortable part is that the code passed review, passed tests, and ran in production for years, and none of that was ever evidence of correctness. It was evidence that the runtime was covering for it.

**Q16 — Model answer:**
A typical unit test constructs the service fresh in `setUp()` or at the top of the test method, exercises it, and asserts on the result. That test can never fail for a lifecycle reason, because it never reuses an instance across the boundary where contamination would appear. It is testing the same conditions that FPM provides — one fresh object, one unit of work — which is exactly the situation in which the bug is invisible.

To be real evidence, the test has to simulate reuse: construct **one** instance, put it through a full unit of work, then put it through a **second** unit of work as if a new request or job had arrived, and assert that the second sees nothing from the first.

```php
public function testBasketDoesNotLeakBetweenRequests(): void
{
    $basket = new BasketService();      // created ONCE, like a worker singleton

    $basket->add('WIDGET');             // "request 1"
    $this->assertCount(1, $basket->items());

    // "request 2" — no re-construction, exactly as a worker would do it
    $this->assertCount(0, $basket->items(), 'state leaked from the previous request');
}
```

Against the buggy implementation that second assertion fails, which is the point: the test now has the power to detect the defect. That is the shape every test in this lesson's challenge takes.

**Q17 — Model answer:**
Three signals, and the limits of each:

`defined('SWOOLE_VERSION')` — tells you the Swoole extension is loaded. It does not tell you that Swoole is actually serving this process; the extension can be present in a php.ini shared with CLI tooling that runs perfectly ordinary short-lived scripts.

`defined('FRANKENPHP_VERSION')` — tells you the FrankenPHP SAPI is active. It does not distinguish FrankenPHP's classic per-request mode from worker mode, which is exactly the distinction that matters.

`php_sapi_name()` — tells you which interface is in use, `'cli'` or `'fpm-fcgi'` and so on. It says nothing about lifetime. `'cli'` covers both a two-second script and a queue worker running for weeks, which are opposite cases.

Each signal answers "what is loaded?" when the question is "how long will this object live?" — a related but different thing, and the gap between them is where the wrong answer comes from. An explicit deployment flag such as `APP_WORKER_MODE=persistent` answers the actual question directly, because it is set by the people who decided how the process is run. It also survives the arrival of a runtime nobody has heard of yet, and it can be flipped in a test so both branches are covered.

## Section D

**Q18 — Answer:**

| Property | Safe under worker mode? | What request 2 observes |
|---|---|---|
| `private array $lineItems = []` | ❌ **Dangerous** | Every line item added by every previous request. A customer who adds one item sees a basket containing other customers' products, and `total()` charges for all of them. |
| `private ?string $couponCode = null` | ❌ **Dangerous** | The coupon applied by whoever last called `applyCoupon()`. A customer who entered no coupon silently receives another customer's 10% discount — the bug leaks revenue rather than data, so monitoring is unlikely to catch it. |
| `private int $callCount = 0` | ❌ **Dangerous** | A monotonically increasing count across all requests handled by the worker. Less damaging than the other two, but any logic that reads it — a cap, a threshold, a metric — is wrong from request 2 onward. |
| `private readonly TaxCalculator $tax` | ✅ **Safe** | The same collaborator, which is correct and intended. It is `readonly`, assigned in the constructor, and `TaxCalculator` is itself immutable and pure, so sharing it across every request is not merely harmless but desirable. |

Three of four properties are unsafe, and the reason is the same in each case: a public method writes to the property and another public method reads it. That single sentence is the test. The `readonly` property passes because nothing can write to it after construction — the language enforces it, so it is not a matter of discipline.

Worth noting that this is not a class that needs a small fix. Three of its four properties are per-checkout data being stored on a service, which means the service is holding what should have been a parameter or a return value. Lesson 6.4 addresses that redesign.

**Q19 — Answer:**
The test proves nothing about lifecycle safety because it constructs `AuditLogger` fresh and calls it exactly once — as a single unit of work. That is the FPM case, and the FPM case is where the bug is invisible by construction. The test would pass identically against a correctly stateless implementation and against this leaking one, which makes it useless for distinguishing them. It confirms only that `record()` appends and `entries()` returns, which nobody doubted.

Rewritten so it has the power to fail:

```php
public function testAuditLoggerDoesNotCarryEntriesIntoTheNextOperation(): void
{
    $logger = new AuditLogger();       // ONE instance, as a worker singleton would be

    // Operation 1
    $logger->record('user.login');
    $logger->record('order.created');
    $this->assertCount(2, $logger->entries());

    // Operation 2 — a new request or job arrives; the instance is NOT rebuilt
    $logger->record('order.shipped');

    $this->assertCount(
        1,
        $logger->entries(),
        'operation 2 sees entries from operation 1 — state leaked across the boundary'
    );
}
```

Against the implementation shown, `entries()` returns three items and the final assertion fails with a message that names the actual defect. The single change that gives the test its power is refusing to re-construct the object between the two operations — because a worker will not re-construct it either.

**Q20 — Answer:**
Two reasons this is the weaker fix:

First, it relies on a call that nothing enforces. `clear()` is an ordinary public method; no type, no interface, and no container mechanism obliges anyone to call it. It is enforced only by every future developer remembering, on every entry path, forever. Add a second entry point — a queue consumer, a scheduled command, a webhook handler, a health check that touches the service — and the guarantee is gone, silently. Transient scope moves the same guarantee into the container, where no caller can forget it because no caller is involved.

Second, it makes the leak part of the public API. Every consumer of `UserSessionService` now has to know that the object arrives dirty and must be reset before use. That is internal lifecycle management leaking into calling code, and it is backwards: the service's job is to present a correct interface regardless of its own history. A stateless design — pass the user through the call, or resolve a fresh instance per request — needs no such knowledge from anybody.

A realistic leak despite the `clear()` call: the request handler calls `clear()` at the top, `authenticate('alice')` runs during the request, and then the request throws an unhandled exception or the response short-circuits before completing. `clear()` has already run for *this* request; it does not run again. The next request begins, calls `clear()`, and — if that call happens after the container has already handed the instance to a service that read `currentUser()` during construction or during early middleware — Alice's identity is what that service captured. More simply still: any code path that resolves the service *before* the handler's `clear()` line, such as a logging middleware that stamps the current user onto every log line, sees the previous request's user. The fix depends on ordering, and ordering is not something the type system checks.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | The lifecycle model is solid. Move on to Lesson 6.2 (Transient vs Singleton Scopes). |
| 14–17 | Re-read README Sections 3 and 4, re-run `examples/02-long-running-worker.php`, then re-attempt Q18–Q20 before continuing. |
| Below 14 | Re-read the README in full and redo the challenge. Every later lesson in Module 6 assumes this model. |
