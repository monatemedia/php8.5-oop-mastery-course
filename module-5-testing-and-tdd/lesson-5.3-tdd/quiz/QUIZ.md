# Quiz — Lesson 5.3: Test-Driven Development (TDD)
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** What is the correct order of the TDD cycle?

- A) Green → Red → Refactor
- B) Refactor → Red → Green
- C) Red → Green → Refactor
- D) Red → Refactor → Green

---

**Q2.** Robert Martin's first rule of TDD states:

- A) You must write one test per class.
- B) You are not allowed to write any production code unless it is to make a failing test pass.
- C) You must refactor after every test.
- D) Tests must be written before interfaces.

---

**Q3.** You write a test for `generateToken()`. The test calls `$this->service->generateToken('alice@example.com')`. The class `PasswordResetService` does not exist yet. PHPUnit reports a fatal error. What phase is this?

- A) Green — the test passes because there are no assertions yet.
- B) Red — a compilation error IS a failing test.
- C) Refactor — you need to clean up the class first.
- D) Skip — you cannot run a test for a non-existent class.

---

**Q4.** The "fake it till you make it" technique means:

- A) Mock the database and pretend the real one works the same.
- B) Return a hardcoded value to make the current test pass, knowing the NEXT test will break the hardcode and force a real implementation.
- C) Write fake tests that always pass until you have time to write real ones.
- D) Skip tests for complex methods and implement them directly.

---

**Q5.** What does "outside-in TDD" mean?

- A) Starting from the innermost utility functions and building outward.
- B) Starting from the outermost behaviour the caller wants and letting the tests pull the design inward, with interfaces emerging from what the anonymous class doubles need.
- C) Writing integration tests before unit tests.
- D) Starting from the database schema and building the domain model around it.

---

**Q6.** During a TDD session for `PasswordResetService`, you write this test:

```php
public function testStoreTokenSetsExpiryOneHourFromNow(): void
{
    $this->service->storeToken('alice@example.com', 'mytoken');
    $record = $this->fakeRepo->find('alice@example.com');
    $this->assertSame('2026-06-01 13:00:00', $record['expires_at']->format('Y-m-d H:i:s'));
}
```

The test is brittle. What does it reveal about the design, and how does TDD fix it?

- A) The test is fine — it just needs a more lenient assertion with `assertEqualsWithDelta`.
- B) The `storeToken()` method uses `new \DateTimeImmutable()` for "now", which changes every second. TDD forces you to inject a `ClockInterface` so the test can provide a fixed time.
- C) The `fakeRepo` needs to be replaced with a real database for time-based testing.
- D) The assertion format is wrong — use `assertInstanceOf(\DateTimeImmutable::class, ...)` instead.

---

**Q7.** A TDD practitioner writes this implementation to pass test 1:

```php
public function generateToken(string $email): string
{
    return str_repeat('a', 64);
}
```

Their colleague says: "This is wrong — it always returns the same token." The TDD practitioner responds: "It passes the current test." Who is correct?

- A) The colleague — hardcoded implementations are never acceptable in TDD.
- B) The TDD practitioner — it is correct for NOW. The next test (`assertNotSame($t1, $t2)`) will break this hardcode and force a real implementation.
- C) Both are wrong — the implementation should use `random_bytes()` from the start.
- D) The colleague — TDD requires the simplest CORRECT implementation, not the simplest passing one.

---

**Q8.** Which of the following is a sign that a TDD step was too large?

- A) The test method has more than one assertion.
- B) The test passes on the first try without any implementation.
- C) You have to write 30+ lines of implementation to make one test pass.
- D) The test uses a data provider.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | Refactoring in TDD means changing the external behaviour of the code while keeping tests green. | |
| 10 | An anonymous class double that defines a method signature IS the interface definition — before the formal PHP interface is extracted. | |
| 11 | In outside-in TDD, you should design all interfaces upfront before writing any tests. | |
| 12 | TDD is appropriate for exploratory "spike" code where you are still learning what the API should look like. | |
| 13 | The `ClockInterface` in PasswordResetService is an example of a design decision FORCED by TDD — without TDD, you might have used `new \DateTimeImmutable()` inline, making expiry tests impossible. | |
| 14 | You should write the test, then CONFIRM it fails (red) before writing any implementation. | |

---

## Section C — Short Answer

**Q15.** Explain the statement "TDD is a design technique, not a testing technique." What design benefit does TDD provide beyond producing a test suite?

*Your answer:*

---

**Q16.** A developer skips the "confirm it fails" step — they write the test and immediately start implementing. Why is this a problem? What can happen?

*Your answer:*

---

**Q17.** You are building `InvoiceService` using TDD. Your first test is:

```php
public function testGenerateInvoiceReturnsAnArray(): void
{
    $invoice = $this->service->generateInvoice(orderId: 1);
    $this->assertIsArray($invoice);
}
```

You implement `generateInvoice()` to return `[]`. The test passes. What is the next test you should write, and why?

*Your answer:*

---

## Section D — Code Reading

**Q18.** The following test was written as part of a TDD session. What behaviour does it specify? What interface method does writing this test define? Does it pass with the naive implementation below?

```php
public function testStoreTokenPersistsViaRepository(): void
{
    $spyRepo = new class implements TokenRepositoryInterface {
        public array $stored = [];
        public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void {
            $this->stored[] = compact('email', 'token', 'expiresAt');
        }
        public function find(string $email): ?array    { return null; }
        public function invalidate(string $email): void {}
    };

    $service = new PasswordResetService($spyRepo, $this->fixedClock);
    $service->storeToken('alice@example.com', 'abc');

    $this->assertCount(1, $spyRepo->stored);
}

// Naive implementation:
public function storeToken(string $email, string $token): void
{
    // empty
}
```

*Your answer:*

---

**Q19.** Trace through the TDD cycle for these two tests in sequence. What happens to the implementation between test A and test B?

```php
// Test A
public function testIsTokenValidReturnsTrueForValidToken(): void
{
    $expiresAt = $this->now->modify('+1 hour');
    $this->fakeRepo->store('alice@example.com', 'tok123', $expiresAt);
    $this->assertTrue($this->service->isTokenValid('alice@example.com', 'tok123'));
}

// Test B
public function testIsTokenValidReturnsFalseForWrongToken(): void
{
    $expiresAt = $this->now->modify('+1 hour');
    $this->fakeRepo->store('alice@example.com', 'correcttoken', $expiresAt);
    $this->assertFalse($this->service->isTokenValid('alice@example.com', 'wrongtoken'));
}
```

*Your answer:*

---

**Q20.** A colleague proposes this alternative to injecting a `ClockInterface`:

```php
class PasswordResetService
{
    private int $nowOverride = 0; // 0 means "use real time"

    public function setNowOverride(int $timestamp): void
    {
        $this->nowOverride = $timestamp;
    }

    private function getNow(): \DateTimeImmutable
    {
        return $this->nowOverride > 0
            ? (new \DateTimeImmutable())->setTimestamp($this->nowOverride)
            : new \DateTimeImmutable();
    }
}
```

Identify three problems with this approach compared to injecting `ClockInterface`.

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
| 1 | **C** | Red → Green → Refactor. Always in this order. You cannot refactor without green tests, and you cannot go green without first going red. |
| 2 | **B** | Rule 1: no production code without a failing test. This rule ensures every line of production code has test coverage. |
| 3 | **B** | A PHP fatal error (class not found, method not found) IS a failing test in TDD. It is the expected red state when the class does not exist yet. |
| 4 | **B** | Fake it: return a hardcode to pass the current test quickly. The NEXT test breaks that hardcode and forces the real implementation. This is valid TDD practice — it keeps steps small. |
| 5 | **B** | Outside-in: start at the outermost behaviour, use doubles for dependencies that do not exist yet, let the interfaces emerge from what the doubles need. |
| 6 | **B** | The test is brittle because `new \DateTimeImmutable()` always returns the current moment — which changes every second. TDD exposes this problem immediately. The fix: inject `ClockInterface` so the test controls "now". |
| 7 | **B** | The TDD practitioner is correct. Hardcoded values are valid in TDD to get to green quickly. The technique is called "Fake It Till You Make It". The next test (`assertNotSame`) will break the hardcode and demand a real implementation. |
| 8 | **C** | Writing 30+ lines to pass one test means the test step was too large. Split the behaviour into smaller pieces — each test should require 1–5 lines of implementation. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | Refactoring means changing the INTERNAL structure while keeping the EXTERNAL behaviour (observable results) unchanged. Tests must stay green — but the external behaviour should not change. |
| 10 | **T** | The anonymous class `new class implements TokenRepositoryInterface { public function store(...) {...} }` defines the method signature. When you extract that to a formal PHP interface, you are formalising what the double already defined. |
| 11 | **F** | Outside-in TDD does NOT design interfaces upfront. Interfaces emerge from what the tests' anonymous class doubles need, one method at a time. Designing upfront is the waterfall approach. |
| 12 | **F** | TDD is NOT good for exploratory spikes. When you do not know what the API should look like, writing tests first adds friction. Write a throwaway spike, learn what you need, discard it, then TDD the real thing. |
| 13 | **T** | Without TDD, the temptation is `new \DateTimeImmutable()` inline. TDD forces you to write the expiry assertion test, which immediately reveals the problem. The design decision (inject a clock) is forced by the test. |
| 14 | **T** | Confirming red is mandatory. If the test passes before any implementation, either the test is wrong (too permissive) or the behaviour already exists. Never skip the red confirmation. |

## Section C

**Q15 — Model answer:**
TDD is a design technique because the test is the first *caller* of the code you are about to write. Before writing `PasswordResetService`, you must write a test that uses it. This immediately exposes design problems: awkward constructors, untestable dependencies created with `new` internally, vague return types, methods that do too many things. These problems would otherwise only surface at integration time. TDD also drives classes toward loose coupling and DI because tight coupling makes tests impossible to write — the test forces a better design as a precondition of passing.

**Q16 — Model answer:**
Skipping the red confirmation can hide a test that never tests anything. If the test passes without implementation, it might be because: the assertion is trivially true (`assertTrue(true)`), an existing method already satisfies the assertion, or the test has a bug that makes it always pass. Without seeing red first, you have no evidence the test would catch a regression. The "confirm red" step is the proof that the test is wired correctly.

**Q17 — Model answer:**
The next test should add a meaningful assertion about the invoice's content — for example, verifying that the array contains an `order_id` key matching the argument, or an `items` key. The reason: `return [];` is the fake-it implementation — it passes the first test but is clearly incomplete. The second test should force a more concrete shape. For example:

```php
public function testGenerateInvoiceContainsOrderId(): void
{
    $invoice = $this->service->generateInvoice(orderId: 42);
    $this->assertArrayHasKey('order_id', $invoice);
    $this->assertSame(42, $invoice['order_id']);
}
```

This test breaks `return []` and forces real data into the result.

## Section D

**Q18 — Answer:**
The test specifies: "when `storeToken()` is called, it must persist the record via the repository by calling `store()`." Writing the anonymous class spy defined the `store(string $email, string $token, \DateTimeImmutable $expiresAt): void` method signature on `TokenRepositoryInterface` — before the interface existed, the anonymous class was the specification.

The naive implementation (empty method body) causes the test to **fail** — `$spyRepo->stored` remains empty and `assertCount(1, ...)` fails. This is the correct red state. The test then forces you to add `$this->repository->store($email, $token, $expiresAt)` to the implementation.

**Q19 — Answer:**
After Test A (valid token returns true):

GREEN (naive): `public function isTokenValid(...): bool { return true; }`

This passes Test A because the token is valid and `true` is correct.

After Test B (wrong token returns false):

The naive `return true;` FAILS Test B — the wrong token must return false but `true` is returned. This forces the real implementation:

```php
public function isTokenValid(string $email, string $token): bool
{
    $record = $this->repository->find($email);
    if ($record === null) return false;
    return $record['token'] === $token;
}
```

The expiry check is NOT added yet — no test demands it. It will be added in Cycle 4 (the expired token test), which will break the current implementation and force: `&& $record['expires_at'] > $this->clock->now()`.

**Q20 — Answer:**
Three problems with the `setNowOverride` approach:

1. **It breaks the DIP and adds test-only code to production:** `setNowOverride()` is a setter that exists purely for tests. Production code now contains a testing hook, violating the principle of keeping test infrastructure out of production code. `ClockInterface` keeps the seam clean — production uses `SystemClock`, tests use `FixedClock`.

2. **It violates the open/closed principle:** the class has conditional behaviour (`nowOverride > 0 ? ... : ...`) that branches between test mode and production mode. Any new behaviour that depends on time must know about this dual-mode logic. With `ClockInterface`, the class always calls `$this->clock->now()` — one code path, no branching.

3. **The override is mutable shared state — not thread-safe and easy to forget to reset:** if one test calls `setNowOverride(...)` and the teardown does not reset it, subsequent tests see the wrong time. An injected `ClockInterface` is immutable per constructor call — each test creates a fresh service with a fresh clock, and there is nothing to reset.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | TDD is internalised. Ready for Lesson 5.4 (Integration Testing). |
| 14–17 | Re-read README Sections 3, 5, and 7. Redo one TDD cycle from scratch before moving on. |
| Below 14 | Complete the challenge (5 full cycles) before retaking. TDD is learned by doing, not reading. |