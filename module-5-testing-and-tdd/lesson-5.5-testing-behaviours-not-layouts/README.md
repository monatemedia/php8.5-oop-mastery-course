# Lesson 5.5 — Testing Behaviours, Not Layouts
> **Module 5: Automated Testing & TDD** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-5.5-testing-behaviours-not-layouts/
├── README.md                                  ← Theory (you are here)
│
├── examples/
│   ├── 01-brittle-vs-resilient-tests.php      ← Same feature, two test styles
│   ├── 02-refactor-without-breaking.php        ← Refactor implementation, tests stay green
│   └── 03-when-to-assert-on-calls.php          ← When invocation assertions are appropriate
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── BrittleTestSuite.php                ← 5 anti-pattern tests to identify and rewrite
│   └── solution/
│       └── ResilientTestSuite.php              ← All 5 tests rewritten to test behaviour
│
└── quiz/
    └── QUIZ.md
```

---

## 1 — The Problem: Tests That Break on Every Refactor

A test suite is meant to give you **confidence when changing code**. But some test suites do the opposite — they break whenever you rename a variable, extract a method, or reorganise a class, even when no observable behaviour changed.

These are **brittle tests**. They test the *layout* of the code — how it is structured internally — rather than *what it does* for callers.

```
Brittle test (tests layout):
  Rename $this->gateway to $this->paymentGateway → test fails

Resilient test (tests behaviour):
  Rename $this->gateway to $this->paymentGateway → test still passes
```

Brittle tests are worse than no tests. They create overhead without safety: you spend time updating them after every refactor, and they give you false confidence that the system is "tested" when the tested things are not the things that matter.

---

## 2 — The Brittleness Spectrum

```
MORE BRITTLE ◄──────────────────────────────────────────► MORE RESILIENT

Tests that assert:                  Tests that assert:
  - private property names            - return values of public methods
  - exact number of constructor args  - exceptions thrown for invalid input
  - exact log message strings         - side effects (was the email sent?)
  - that a specific method was called - the result in the database after a write
  - internal cache state              - the HTTP status code of a response
```

The rule: **test what callers can observe**. A caller can observe a return value, an exception, and the effect on a collaborator (via a spy). A caller cannot observe a private property, a log message wording, or which internal helper method was called.

---

## 3 — Anti-Pattern 1: Asserting on Constructor Parameters

```php
// ❌ BRITTLE — tests how the class is structured, not what it does
public function testServiceHasThreeConstructorParameters(): void
{
    $reflection = new \ReflectionClass(OrderService::class);
    $params     = $reflection->getConstructor()->getParameters();

    $this->assertCount(3, $params);
}
```

**Why this breaks:** Add a `LoggerInterface` parameter to `OrderService` — entirely reasonable — and this test fails. No behaviour changed. The test punishes improvement.

**Why this exists:** Developers sometimes write this to "verify DI is set up correctly." That is not what the test achieves. A container wiring integration test (Lesson 5.4) verifies DI correctly.

**The fix:** Delete it. There is no useful behaviour version of this test. If you want to verify the constructor accepts a logger, write a test that exercises behaviour that depends on the logger.

---

## 4 — Anti-Pattern 2: Asserting on Private Properties

```php
// ❌ BRITTLE — accesses private state directly
public function testServiceStoresGatewayInProperty(): void
{
    $gateway = new class implements PaymentGatewayInterface {
        public function charge(float $amount, string $token): bool { return true; }
    };

    $service    = new OrderService($gateway, $this->nullMailer(), $this->nullLogger());
    $reflection = new \ReflectionProperty(OrderService::class, 'gateway');
    $reflection->setAccessible(true);

    $this->assertSame($gateway, $reflection->getValue($service));
}
```

**Why this breaks:** Rename the property `$gateway` → `$paymentGateway`, or replace it with a property hook — test fails. The class's behaviour is unchanged; the private name changed.

**Why this exists:** Developers want to verify that constructor injection works. The correct way is to test the behaviour that depends on the injected value — not the plumbing that stores it.

**The fix:** Test that the service USES the gateway correctly, not that it stores it:

```php
// ✅ RESILIENT — tests behaviour
public function testServiceChargesCorrectAmountThroughGateway(): void
{
    $spyGateway = new class implements PaymentGatewayInterface {
        public array $calls = [];
        public function charge(float $amount, string $token): bool {
            $this->calls[] = compact('amount', 'token');
            return true;
        }
    };

    $service = new OrderService($spyGateway, $this->nullMailer(), $this->nullLogger());
    $service->placeOrder(productId: 1, paymentToken: 'tok_4242');

    $this->assertSame(29.99, $spyGateway->calls[0]['amount']);
}
```

---

## 5 — Anti-Pattern 3: Over-Specified Mock Expectations

PHPUnit's `createMock()` lets you assert that a method was called with exact arguments — this is useful but frequently overused:

```php
// ❌ BRITTLE — over-specified mock
public function testServiceLogsEveryStep(): void
{
    $mockLogger = $this->createMock(LoggerInterface::class);

    $mockLogger->expects($this->exactly(3))
        ->method('log')
        ->withConsecutive(
            ['info', 'Starting payment processing'],
            ['info', 'Gateway charged successfully'],
            ['info', 'Email sent to alice@example.com']
        );

    $service = new OrderService($this->stubGateway(), $this->nullMailer(), $mockLogger);
    $service->placeOrder(1, 'tok_4242');
}
```

**Why this breaks:** Change any log message wording, merge two log calls into one, add a debug log — test fails. These log messages are internal detail. They are not observable by the caller. They are not a contract.

**The exception:** Call-count assertions ARE appropriate for side effects that are part of the contract:

```php
// ✅ RESILIENT — side-effect count is part of the contract
public function testServiceSendsExactlyOneConfirmationEmail(): void
{
    $spyMailer = new class implements MailerInterface {
        public array $sent = [];
        public function send(string $to, string $subject, string $body): bool {
            $this->sent[] = compact('to', 'subject', 'body');
            return true;
        }
    };

    $service = new OrderService($this->stubGateway(), $spyMailer, $this->nullLogger());
    $service->placeOrder(1, 'tok_4242');

    // ✅ Appropriate: "exactly one email" is observable contract behaviour
    $this->assertCount(1, $spyMailer->sent);
    $this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
}
```

---

## 6 — The Observable Boundary Rule

The line between "layout" and "behaviour" is the class's **observable boundary**: everything a caller can see.

```
Observable (test this):
  ✅ Return value of a public method
  ✅ Exception thrown for invalid input
  ✅ Arguments passed to a collaborator (spy)
  ✅ Whether a collaborator was called at all (spy count)
  ✅ State visible through a public getter or query method
  ✅ HTTP response status and body (for controllers)

Not observable (do not test this):
  ❌ Private property names or values
  ❌ Private method names or call counts
  ❌ Which constructor parameter name was used
  ❌ Exact log message strings (unless contractual)
  ❌ Internal cache state
  ❌ How many database queries were made internally (usually)
  ❌ Whether an optimisation (e.g. memoisation) is in place
```

The test should read like a specification: "Given X input, the system does Y." It should never read like an X-ray of the class internals.

---

## 7 — Refactoring with Confidence

The purpose of a test suite is to let you change code with confidence. Behaviour tests survive refactors; layout tests do not.

```
Refactor: extract a private helper method
  Brittle (tests private method): ❌ fails
  Resilient (tests return value): ✅ passes

Refactor: rename private property $gateway → $paymentProcessor
  Brittle (uses ReflectionProperty): ❌ fails
  Resilient (tests charge amount):   ✅ passes

Refactor: add a caching layer inside the service
  Brittle (asserts exact query count): ❌ fails
  Resilient (tests returned value):    ✅ passes

Refactor: split a god-class into two smaller classes
  Brittle (asserts on constructor params): ❌ fails
  Resilient (tests public API contract):   ✅ passes
```

**The test suite is healthy when: refactoring the internals of a class without changing its observable contract leaves all tests green.**

---

## 8 — When Call Assertions ARE Appropriate

Not all call assertions are brittle. The test for "was the email sent?" is legitimate because:

1. Sending an email is part of the class's **observable contract** — callers care that it happens
2. The recipient address and subject are **observable values** — they flow from the inputs
3. The exact count (one email per order) is a **business rule**, not an implementation detail

The test becomes brittle when it asserts things that are NOT part of the contract:

```php
// ✅ Contract — the email IS sent, to the right address
$this->assertCount(1, $spyMailer->sent);
$this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);

// ❌ Not contract — the exact subject wording is internal
$this->assertSame('Your order #12345 has been confirmed', $spyMailer->sent[0]['subject']);
// (unless the subject format is explicitly contractual and tested separately)

// ❌ Not contract — the logger is not observable by the caller
$this->assertCount(3, $spyLogger->logged); // why 3? what if you add a debug line?
```

**The test for "whether" a side effect occurred is usually a contract. The test for "exactly how" (wording, count of internal log calls) is usually not.**

---

## 9 — The Test-to-Implementation Ratio

A healthy test suite has a ratio where tests cover **behaviours** rather than lines of code one-to-one. An over-specified suite has more test code than production code, and most of the excess is layout tests.

Signs of an over-specified suite:
- Test names describe method names rather than behaviours (`testLog()`, `testConstructor()`)
- Tests use `ReflectionClass` or `ReflectionProperty`
- Tests break whenever a private method is renamed
- Every log call is asserted — tests break when log messages are reworded
- Mocks assert exact call sequences with `withConsecutive()`
- Tests pass even when the class is completely replaced with a stub

Signs of a healthy suite:
- Test names describe outcomes (`testOrderConfirmationEmailIsSentOnSuccess()`)
- Tests use real inputs and assert real outputs or side effects
- A complete internal refactor leaves all tests green
- Tests would fail if the class were replaced with a stub that returned hardcoded values

---

## 10 — Quick Reference

```
The observable boundary rule:
  Test what the class RETURNS or DOES TO its collaborators.
  Do not test HOW it achieves that internally.

Anti-pattern checklist:
  ❌ new \ReflectionClass($class)->getConstructor()->getParameters() → assertCount(N)
  ❌ new \ReflectionProperty($class, 'somePrivateProp') → assertSame(...)
  ❌ $mock->expects($this->exactly(3))->method('log') for internal logging
  ❌ ->withConsecutive(['info', 'Step 1'], ['info', 'Step 2'], ...) for log messages
  ❌ asserting the exact number of private method calls

Call assertions that ARE appropriate:
  ✅ $this->assertCount(1, $spyMailer->sent)        // "one email sent" is a contract
  ✅ $this->assertSame('a@b.com', $sent[0]['to'])   // recipient is observable
  ✅ $this->assertEmpty($spyMailer->sent)            // "no email on failure" is a contract
  ✅ $this->assertCount(1, $spyRepo->stored)         // "one record saved" is a contract

Refactor test:
  After any internal refactor that does NOT change the public API:
  → Run the tests. All should be green.
  → If any fail: they were testing layout, not behaviour. Rewrite them.
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 3–6 are the most important
- [ ] Run and study `examples/01-brittle-vs-resilient-tests.php` — compare the two styles side by side
- [ ] Run and study `examples/02-refactor-without-breaking.php` — watch the brittle tests fail and the resilient ones hold
- [ ] Run and study `examples/03-when-to-assert-on-calls.php` — learn the legitimate call assertion cases
- [ ] Read `challenge/CHALLENGE.md` — identify each anti-pattern before looking at the solution
- [ ] Complete `challenge/starter/BrittleTestSuite.php` (rewrite the 5 brittle tests)
- [ ] Compare with `challenge/solution/ResilientTestSuite.php`
- [ ] Complete `quiz/QUIZ.md` cold

---

*This is the final lesson of Module 5. The complete testing mindset: unit tests with doubles (5.1–5.2), TDD (5.3), integration tests (5.4), and now the discipline of testing behaviour not layout (5.5).*