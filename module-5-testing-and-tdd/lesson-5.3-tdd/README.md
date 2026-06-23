# Lesson 5.3 — Test-Driven Development (TDD)
> **Module 5: Automated Testing & TDD** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-5.3-tdd/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-red-green-refactor.php          ← One complete TDD cycle, annotated
│   ├── 02-outside-in-tdd.php              ← Start from behaviour, pull design inward
│   └── 03-tdd-with-doubles.php            ← Writing tests before interfaces exist
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── PasswordResetServiceTest.php   ← You implement the service as tests pass
│   └── solution/
│       └── PasswordResetServiceTest.php   ← Full TDD walkthrough with implementation
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — Section 3 (the three rules) and Section 5 (outside-in) are the densest.
2. Run `examples/01-red-green-refactor.php` step by step — read the comments, not just the code.
3. Work through the challenge **one test at a time**. Write the test, watch it fail, make it pass, then move on.
4. Do NOT write the implementation before you have a failing test for it.
5. Take the quiz cold.

---

## 1 — What TDD Is

Test-Driven Development is a discipline where you write the test **before** you write the code it tests. The test fails (Red), you write the minimum implementation to make it pass (Green), then you clean up (Refactor) — then repeat for the next behaviour.

```
RED ──► GREEN ──► REFACTOR ──► RED ──► GREEN ──► REFACTOR ──► ...
```

TDD is not primarily a testing technique. It is a **design technique** that produces tests as a by-product. The test is the first caller of the code you are about to write, which forces you to think about the API from the outside before committing to an implementation.

---

## 2 — The Three Phases

### 🔴 Red — Write a failing test

Write a test for the next small unit of behaviour your class needs. The test must:
- Fail immediately (the code does not exist yet, or does not do this yet)
- Express exactly the behaviour you want in terms of inputs and outputs
- Be the smallest useful step forward

```php
// The class PasswordResetService does not exist yet.
// Write the test anyway — this describes what it must do.
public function testGenerateTokenReturns64CharacterString(): void
{
    $token = $this->service->generateToken('alice@example.com');

    $this->assertIsString($token);
    $this->assertSame(64, strlen($token));
}
```

Running this now produces: `Error: Class "PasswordResetService" not found` — that IS the red phase.

### 🟢 Green — Write the minimum code to pass

Write the **simplest possible code** that makes the failing test pass. Nothing more. Resist the urge to build the whole system. If returning a hardcoded value makes the test pass, do that for now — the next test will force you to generalise.

```php
class PasswordResetService
{
    public function generateToken(string $email): string
    {
        return bin2hex(random_bytes(32)); // 32 bytes = 64 hex chars
    }
}
```

Test passes. Green.

### 🔵 Refactor — Clean up without breaking tests

With the tests green, improve the code without changing its external behaviour:
- Extract a constant for `32` (byte count)
- Rename a confusing variable
- Remove duplication
- Apply SOLID principles

After every change, run the tests. If they go red, undo.

```php
class PasswordResetService
{
    private const TOKEN_BYTES = 32;

    public function generateToken(string $email): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }
}
```

Still green. Refactor complete.

---

## 3 — The Three Rules of TDD (Robert Martin)

1. **You are not allowed to write any production code unless it is to make a failing test pass.**
2. **You are not allowed to write more of a test than is sufficient to fail** (compilation failure counts as failing).
3. **You are not allowed to write more production code than is sufficient to make the one failing test pass.**

These rules feel restrictive but produce a specific outcome: you always have a suite of tests that covers every line of production code, because no production code exists unless a test demanded it.

In practice, especially when learning, you can relax Rule 2 slightly — write a complete test method before implementing. The spirit of the rules is the constraint: **test first, implement second, always.**

---

## 4 — Why TDD Produces Better Design

The test is the **first client** of the code. Before you write `PasswordResetService`, you must write a test that uses it. That test will immediately reveal:

- If the constructor is awkward to call (too many parameters)
- If the return type is vague (`mixed` when a string is needed)
- If a method does too many things (hard to write a single assertion)
- If you need a dependency you had not thought of (the test makes you define the interface)

```php
// Without TDD — you discover this problem at integration time
class PasswordResetService {
    public function doReset(string $email): void {
        $db   = new MySQLDatabase();          // ← untestable
        $mail = new SmtpMailer();             // ← untestable
        // ...all logic mixed together
    }
}

// With TDD — the test FORCES you to inject dependencies
// because you cannot write the test without being able to swap them
class PasswordResetService {
    public function __construct(
        private TokenRepositoryInterface $tokens,
        private MailerInterface          $mailer,
        private ClockInterface           $clock    // ← you discover you need this for expiry
    ) {}
}
```

TDD drives your design toward loosely coupled, injectable, testable classes — the same goal as Modules 1–4 of this course.

---

## 5 — Outside-In TDD

Outside-in (also called "London School" or "mockist" TDD) starts from the behaviour the **user or caller** wants and works inward, defining interfaces as you go.

```
Start here:
  "I want a PasswordResetService that sends reset emails"

  → Write a test for the service's public API
  → The test discovers you need a TokenRepositoryInterface
  → The test discovers you need a MailerInterface
  → The test discovers you need a ClockInterface
  → Stub/spy all three in the test (they do not exist yet — that is fine)
  → Make the test pass by implementing PasswordResetService
  → Now write tests for the TokenRepository, Mailer, Clock separately
```

The interfaces emerge from what the tests need, not from upfront design.

```php
// Step 1: write the test — interfaces do not exist yet
public function testSendResetEmailStoresTokenAndSendsEmail(): void
{
    // You are defining the interfaces YOU NEED right here, in the test.
    // The anonymous classes are placeholders until you implement the real thing.

    $spyTokenRepo = new class implements TokenRepositoryInterface {
        public array $stored = [];
        public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void {
            $this->stored[] = compact('email', 'token', 'expiresAt');
        }
        public function find(string $email): ?array { return null; }
        public function invalidate(string $email): void {}
    };

    $spyMailer = new class implements MailerInterface {
        public array $sent = [];
        public function send(string $to, string $subject, string $body): bool {
            $this->sent[] = compact('to', 'subject', 'body');
            return true;
        }
    };

    $service = new PasswordResetService($spyTokenRepo, $spyMailer, new SystemClock());

    $service->sendResetEmail('alice@example.com');

    $this->assertCount(1, $spyTokenRepo->stored);
    $this->assertSame('alice@example.com', $spyTokenRepo->stored[0]['email']);
    $this->assertCount(1, $spyMailer->sent);
}
```

Writing this test defines three interfaces (`TokenRepositoryInterface`, `MailerInterface`, `ClockInterface`) before a single line of production code exists. The design emerges from the tests.

---

## 6 — TDD Step Sizes

Beginners often write steps that are too large. Each step should be the smallest possible behaviour worth testing.

```
❌ Too large (one step):
   "Test that the full checkout flow works end-to-end"

✅ Right size (five steps):
   Test 1: checkout() throws DomainException for unknown product
   Test 2: checkout() returns ['success' => false] for declined payment
   Test 3: checkout() returns ['success' => true] for valid payment
   Test 4: checkout() sends one confirmation email on success
   Test 5: checkout() sends the email to the customer's address
```

If you find yourself writing a lot of implementation to make a test pass, the test step was too large. Split it.

---

## 7 — The "Fake It Till You Make It" Technique

When you need to get to green quickly and the real implementation is not obvious, return a hardcoded value. The next test will break that hardcode and force a real implementation.

```php
// Test 1
public function testGenerateTokenReturns64CharacterString(): void {
    $token = $this->service->generateToken('alice@example.com');
    $this->assertSame(64, strlen($token));
}

// Fake it — hardcoded 64-char string. Test passes.
public function generateToken(string $email): string {
    return str_repeat('a', 64);
}

// Test 2
public function testGenerateTokenReturnsDifferentTokenEachCall(): void {
    $t1 = $this->service->generateToken('alice@example.com');
    $t2 = $this->service->generateToken('alice@example.com');
    $this->assertNotSame($t1, $t2);
}

// Hardcode breaks — now forced to implement properly
public function generateToken(string $email): string {
    return bin2hex(random_bytes(32)); // 32 bytes = 64 hex chars
}
```

This technique is valid and encouraged. It keeps each step small.

---

## 8 — When NOT to Use TDD

TDD is a tool, not a religion. Some situations where it adds friction rather than value:

| Situation | Why TDD is hard | What to do instead |
|-----------|----------------|--------------------|
| Exploratory / spike code | You don't know what the API should look like yet | Write throwaway code, then TDD the real thing |
| Framework or library configuration | The framework drives the API, not tests | Write integration/acceptance tests afterwards |
| Pure UI/rendering code | Hard to express visually in assertions | Manual testing + snapshot tools |
| Trivial getters/setters | No logic to drive | Skip — test at the call site if needed |
| Tightly-coupled legacy code | Cannot inject doubles without refactoring first | Characterisation tests first, then TDD |

The heuristic: if you can describe the behaviour in one sentence as "given X, it returns Y", TDD works. If the outcome is inherently visual or emergent, TDD adds friction.

---

## 9 — The PasswordResetService: The Challenge Subject

The challenge asks you to build this service from scratch using TDD. Five behaviours, five tests, one cycle at a time:

```
Test 1: generateToken() returns a 64-character hex string
Test 2: storeToken() persists the token via the repository
Test 3: isTokenValid() returns true for a stored, unexpired token
Test 4: isTokenValid() returns false for an expired token
Test 5: invalidateToken() marks the token as used
```

The interfaces you will need — defined by the tests before you implement them:

```php
interface TokenRepositoryInterface
{
    public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void;
    public function find(string $email): ?array;  // ['token', 'expires_at', 'used']
    public function invalidate(string $email): void;
}

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
```

`ClockInterface` is the key insight TDD surfaces: you cannot test token expiry without controlling time. The real clock always returns "now", which makes "is the token expired?" untestable. Injecting a fake clock solves this cleanly.

---

## 10 — Quick Reference

```
The cycle:
  1. RED    — write a failing test for the next behaviour
  2. GREEN  — write the minimum code to make it pass
  3. REFACTOR — clean up; tests must stay green
  4. REPEAT

The discipline:
  - No production code without a failing test
  - Smallest possible step each cycle
  - Refactor only when green

The design payoff:
  - Awkward tests reveal awkward APIs — fix the API, not the test
  - Hard-to-inject dependencies expose tight coupling — inject them instead
  - Interfaces emerge from what tests need — not from upfront guessing

When it works best:
  - Business logic with clear inputs/outputs
  - Services with injectable dependencies
  - Value objects and domain rules

When to skip it:
  - Exploratory spikes (throw away afterwards, TDD the real version)
  - Framework config and glue code
  - Purely visual output
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 3, 5, and 7 are the most important
- [ ] Run `examples/01-red-green-refactor.php` and read every comment
- [ ] Run `examples/02-outside-in-tdd.php` and observe how interfaces emerge from the test
- [ ] Run `examples/03-tdd-with-doubles.php`
- [ ] Read `challenge/CHALLENGE.md` — understand the 5-step TDD session before starting
- [ ] Work through `challenge/starter/PasswordResetServiceTest.php` one test at a time: write test → fail → implement → pass → next test
- [ ] Only open `challenge/solution/PasswordResetServiceTest.php` after all 5 tests pass
- [ ] Complete `quiz/QUIZ.md` cold

---

*Next lesson: **5.4 — Integration Testing** — test the boundaries where your code meets real infrastructure.*