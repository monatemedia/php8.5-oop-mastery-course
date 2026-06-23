# Quiz — Lesson 5.5: Testing Behaviours, Not Layouts
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** What makes a test "brittle"?

- A) It runs slowly.
- B) It tests the internal structure of a class — property names, constructor parameter counts, exact log message strings — rather than observable behaviour. It breaks when the internals are refactored even though no external behaviour changed.
- C) It uses anonymous classes instead of PHPUnit mocks.
- D) It has more than one assertion.

---

**Q2.** A developer refactors `OrderService` by renaming a private property `$gateway` → `$paymentProcessor`. No public method signatures change. A test that used `ReflectionProperty` to read `$gateway` now fails. This is an example of:

- A) A test correctly catching a breaking change.
- B) A brittle test that tests layout rather than behaviour — private names are not observable by callers and should not be asserted on.
- C) A test that should have used `assertSame` instead of `ReflectionProperty`.
- D) An integration test that should have been a unit test.

---

**Q3.** Which of the following is on the CORRECT side of the observable boundary?

- A) Asserting that `$reflection->getConstructor()->getParameters()` returns 3 elements.
- B) Asserting that `$service->processOrder()` returns `['success' => true]` when the gateway succeeds.
- C) Asserting that the private `$cache` property is populated after calling a method.
- D) Asserting that `$mock->expects($this->exactly(3))->method('log')` for internal logging steps.

---

**Q4.** A test asserts `$this->assertCount(1, $spyMailer->sent)` after calling `$service->placeOrder(...)`. Which of the three questions confirms this is a LEGITIMATE call assertion?

- A) "Is the email sent written in a publicly visible log?"
- B) "Is this call part of the class's contract? Would it be a bug if no email was sent?"
- C) "Does the spy use an anonymous class?"
- D) "Is the mailer a third-party library?"

---

**Q5.** You have a test that asserts `$this->assertMatchesRegularExpression('/^[A-F0-9]{16}$/', $result['transaction_id'])`. The team decides to switch from 16-char hex IDs to UUID format. The behaviour is unchanged — a non-null ID is returned. What should the test have asserted instead?

- A) `$this->assertIsString($result['transaction_id'])` and `$this->assertNotEmpty($result['transaction_id'])`
- B) `$this->assertSame(16, strlen($result['transaction_id']))`
- C) `$this->assertMatchesRegularExpression('/^[a-zA-Z0-9-]+$/', $result['transaction_id'])`
- D) The test should have been deleted entirely.

---

**Q6.** A test uses `$mock->expects($this->exactly(3))->method('log')->withConsecutive(...)` to assert that three specific log messages are written in order. After a refactor that adds a debug log call between steps 1 and 2, the test fails. What is the root problem?

- A) The mock was created with `createMock` instead of `getMockBuilder`.
- B) The test asserts on internal logging mechanics — the exact count and wording of log calls — which are implementation details, not observable contract behaviour.
- C) The test should use `withAnyParameters()` instead of `withConsecutive`.
- D) The logger should be injected via a setter, not the constructor.

---

**Q7.** Which of these call assertions is NOT a legitimate contract assertion?

- A) `$this->assertCount(1, $spyMailer->sent)` after a successful order
- B) `$this->assertEmpty($spyMailer->sent)` after a failed payment
- C) `$this->assertSame('alice@example.com', $spyMailer->sent[0]['to'])` verifying the recipient
- D) `$this->assertSame(3, count($spyLogger->logged))` verifying exactly three internal log calls

---

**Q8.** After a complete internal refactor of `InvoiceService` — extracting private helpers, renaming properties, adding a caching layer — all tests in a suite still pass. This is a sign that:

- A) The refactor introduced no bugs.
- B) The test suite tests behaviour rather than layout — it is resilient to internal changes.
- C) The test suite is too permissive and probably doesn't cover edge cases.
- D) The caching layer was not actually used.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | Asserting that a spy's `$sent` array has count 1 is always brittle because it ties the test to the implementation. | |
| 10 | `new \ReflectionProperty($class, 'privateField')->getValue($obj)` is a sign of a layout test. | |
| 11 | A test that checks the exact log message wording is usually testing internal mechanics, not contract behaviour. | |
| 12 | If a complete internal refactor (no API changes) makes any test fail, that test was testing layout, not behaviour. | |
| 13 | Asserting `assertIsString($id)` and `assertNotEmpty($id)` is more resilient than asserting the exact format of an ID. | |
| 14 | A test suite with many brittle tests gives more confidence than one with fewer resilient tests. | |

---

## Section C — Short Answer

**Q15.** Explain the "observable boundary" rule in two sentences. Give one concrete example of something that is observable and one that is not.

*Your answer:*

---

**Q16.** A developer defends a reflection-based constructor test: "We need to know that the class has exactly three dependencies. If someone adds a fourth, it means the class is doing too much — SRP violation." Critique this reasoning and suggest a better approach.

*Your answer:*

---

**Q17.** Describe the difference between these two assertions and explain which is more resilient and why:

```php
// Assertion A
$this->assertSame('Your order #12345 has been confirmed', $spyMailer->sent[0]['subject']);

// Assertion B
$this->assertStringContainsString('#12345', $spyMailer->sent[0]['subject']);
```

*Your answer:*

---

## Section D — Code Reading

**Q18.** Identify every anti-pattern in the following test. Name the anti-pattern for each.

```php
public function testUserServiceWiring(): void
{
    $service    = new UserService($this->fakeRepo(), $this->stubHasher(), $this->nullLogger());
    $reflection = new \ReflectionClass(UserService::class);

    // Check 1
    $params = $reflection->getConstructor()->getParameters();
    $this->assertCount(3, $params);
    $this->assertSame('repository', $params[0]->getName());

    // Check 2
    $prop = new \ReflectionProperty(UserService::class, 'hasher');
    $prop->setAccessible(true);
    $this->assertInstanceOf(PasswordHasherInterface::class, $prop->getValue($service));

    // Check 3
    $mockLogger = $this->createMock(LoggerInterface::class);
    $mockLogger->expects($this->exactly(2))
        ->method('log')
        ->withConsecutive(
            ['info', 'Creating user'],
            ['info', 'User alice created']
        );
}
```

*Your answer:*

---

**Q19.** Rewrite the following brittle test as a resilient behaviour test. The class under test is `CacheService::get(string $key)` which returns the cached value or fetches it from the repository and caches it.

```php
// BRITTLE:
public function testCacheGetCallsFetchOnCacheMiss(): void
{
    $mockRepo = $this->createMock(RepositoryInterface::class);
    $mockRepo->expects($this->once())
        ->method('fetch')
        ->with('product:42')
        ->willReturn(['id' => 42, 'name' => 'Widget']);

    $cache   = new CacheService($mockRepo);
    $result  = $cache->get('product:42');

    $this->assertSame(['id' => 42, 'name' => 'Widget'], $result);
}
```

*Your answer (write the resilient test):*

---

**Q20.** A colleague argues: "Testing behaviours, not layouts is just a guideline. If we test everything — including internals — we get higher coverage and more confidence." Respond with a concrete counterargument, including an example of how layout tests can actually REDUCE confidence.

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
| 1 | **B** | Brittle tests test the internal structure. They break on valid refactors that change nothing observable — private names, constructor shapes, log wording — producing noise that masks real failures. |
| 2 | **B** | Renaming a private property is a valid refactor. Private names are not part of the public contract. A test that fails after this rename was testing layout. |
| 3 | **B** | A return value of a public method is observable by callers — it IS the contract. The other options all look inside the class boundary. |
| 4 | **B** | The three-question test: Is it contractual (YES — callers care)? Is it verifiable (YES — via spy)? Would absence be a bug (YES — undelivered order = bug)? All three YES → legitimate. |
| 5 | **A** | The CONTRACT is: "a non-empty string ID is returned." The FORMAT (hex, UUID, etc.) is an implementation detail. `assertIsString` + `assertNotEmpty` tests the contract without caring about format. |
| 6 | **B** | Log calls are internal mechanics. Their count and exact wording are not observed by callers. This test was asserting on internal structure dressed up as behaviour. |
| 7 | **D** | Three internal log calls is an implementation detail — it changes whenever a debug statement is added. A → C are all legitimate contract assertions. |
| 8 | **B** | If internal refactoring leaves all tests green, the tests are behaviour-focused. This is the correct outcome: refactor with confidence, tests signal only when the contract breaks. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | `assertCount(1, $spyMailer->sent)` asserts the OBSERVABLE contract — exactly one email is sent. This is legitimate. Brittle tests use reflection or exact internal call sequences. |
| 10 | **T** | Using `ReflectionProperty` to read a private field is a textbook layout test. Private field names are internal details. |
| 11 | **T** | Log messages are internal prose. Unless the message is a documented contract (e.g. a compliance audit format), exact wording should not be tested. |
| 12 | **T** | This is the definition of a layout test: it fails when the internal structure changes even though observable behaviour does not. A behaviour test should survive any internal refactor that preserves the public API. |
| 13 | **T** | `assertIsString` + `assertNotEmpty` tests the contract (a non-empty string ID) without coupling to any particular format. Changing from hex to UUID will not break it. |
| 14 | **F** | Brittle tests give LESS confidence, not more. They break on valid refactors, generating noise. Developers learn to ignore failing tests ("it'll fix itself") or spend time updating layout tests instead of writing real coverage. This is a false sense of security. |

## Section C

**Q15 — Model answer:**
The observable boundary is everything a caller can see when they use the class through its public API. Test what the class returns from a public method, what exception it throws, and what it does to a collaborator (via a spy). Example of observable: `$service->placeOrder()` returns `['success' => true]` — a caller reads this value. Example of not observable: the private property `$this->gateway` is assigned in the constructor — no caller can read this without reflection.

**Q16 — Model answer:**
The reasoning is flawed in two ways. First, if SRP violation is the concern, the right signal is that the class is hard to test (too many dependencies make `setUp()` cumbersome) or that it is hard to name clearly — not that a constructor parameter count exceeds three. Second, a test that asserts `assertCount(3, $params)` does not actually detect SRP violations — a class can violate SRP with only one dependency, or respect SRP with five. The test also breaks when a legitimate improvement adds a fourth dependency (e.g. a logger or a clock), punishing the developer. The better approach: write tests that exercise the class's actual behaviours. If the test suite requires eight doubles, that IS the signal that the class does too much — no reflection needed.

**Q17 — Model answer:**
Assertion A asserts the EXACT subject line, including the literal order number `#12345`. If the format changes to `"Order confirmation: #12345"` or `"#12345 — your order is confirmed"`, Assertion A fails even though the information conveyed is identical. Assertion B is more resilient: it tests that the order number appears somewhere in the subject, which is the actual contract — the customer must be able to identify their order. Assertion B survives any wording change that preserves the key identifier. The rule: assert the minimum needed to verify the contract, not the maximum possible.

## Section D

**Q18 — Answer:**
Three anti-patterns:

1. **Check 1 (constructor parameter count + parameter name)**: `assertCount(3, $params)` is AP-1 (constructor parameter count). `assertSame('repository', $params[0]->getName())` is a variant — asserting on constructor parameter NAMES, which are an internal detail. Both break when a parameter is added or renamed.

2. **Check 2 (private property via reflection)**: AP-2. `ReflectionProperty` reads the `$hasher` private field. This breaks when the property is renamed (e.g. `$passwordHasher`). The correct test is to use the service and verify that hashing behaviour works — not that the property stores the right type.

3. **Check 3 (exact log message sequence with mock)**: AP-3. The mock asserts exact call count (2) and exact message strings in exact order. Breaks when a debug log is added, messages are reworded, or two log calls are merged. Also, the mock is set up but the test never calls the service — this mock expectation can never be verified (the method call is missing from the test body entirely).

**Q19 — Resilient rewrite:**

```php
public function testGetReturnsCachedValueOnCacheMiss(): void
{
    // Fake repository — real lookup logic, no SQL
    $fakeRepo = new class implements RepositoryInterface {
        public function fetch(string $key): ?array {
            return ['id' => 42, 'name' => 'Widget'];
        }
    };

    $cache  = new CacheService($fakeRepo);
    $result = $cache->get('product:42');

    // ✅ Assert the RETURN VALUE — the observable outcome
    $this->assertSame(['id' => 42, 'name' => 'Widget'], $result);
}

// Optional: test caching behaviour (second call does not fetch from repo)
public function testGetReturnsSameValueOnSecondCall(): void
{
    $callCount = 0;
    $fakeRepo  = new class($callCount) implements RepositoryInterface {
        public function __construct(private int &$count) {}
        public function fetch(string $key): ?array {
            $this->count++;
            return ['id' => 42, 'name' => 'Widget'];
        }
    };

    $cache = new CacheService($fakeRepo);
    $first  = $cache->get('product:42');
    $second = $cache->get('product:42');

    // ✅ Both calls return the same value (correct outcome)
    $this->assertSame($first, $second);
    // ✅ We may also verify the cache actually cached (repo called once)
    // but we assert on the OUTCOME (call count = 1), not "fetch was called"
    $this->assertSame(1, $callCount);
}
```

The key difference: the brittle version asserts that `fetch` was called with `expects($this->once())` — any caching optimisation that avoids the call would break this. The resilient version asserts on the return value. The optional second test is acceptable because "fetch called once" IS an observable contract (caching behaviour), not a private implementation detail.

**Q20 — Model answer:**
The argument confuses quantity of tests with quality of tests. Layout tests do not increase confidence — they create false confidence. Here is why they actively reduce it:

When layout tests break on every refactor, developers learn to ignore red tests or fix them mechanically without understanding them. The test suite cries wolf so often that real failures blend in. Worse, the layout tests pass even when the class is completely broken — a class that stores its dependencies correctly but has a bug in its core logic will sail through reflection-based tests while failing in production.

Concrete example: a test suite has twenty tests for `OrderService` — five check constructor parameters, five check private properties, and ten check exact log messages. A developer introduces a bug: `placeOrder()` no longer sends the confirmation email. The twenty layout tests all pass (the constructor is fine, the properties are fine, the log messages are fine). The five resilient behaviour tests that should exist — `testEmailSentOnSuccess`, `testNoEmailOnDecline`, etc. — were never written because the developer felt "covered" by twenty tests. The bug ships.

The correct philosophy: fewer, resilient tests that cover the actual contract give more confidence than many layout tests that tell you nothing about what the class does.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Strong instinct for the observable boundary. Ready for Module 6. |
| 14–17 | Re-read Sections 3–8 of the README, then redo the challenge. |
| Below 14 | Work through Example 01 carefully — apply the refactor and watch the brittle tests break. That experience is the lesson. |