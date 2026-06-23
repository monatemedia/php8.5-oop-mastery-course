# Quiz — Lesson 5.2: Unit Testing with Fakes and Stubs
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** What is the defining characteristic of a unit test?

- A) It runs in under 100ms.
- B) It tests exactly one method.
- C) It tests one class in isolation, with all dependencies replaced by test doubles.
- D) It uses PHPUnit's built-in mocking framework.

---

**Q2.** Which double type is best described as "records calls made to it so the test can assert on them afterwards"?

- A) Fake
- B) Stub
- C) Spy
- D) Null Object

---

**Q3.** You are testing `OrderService::placeOrder()`. The payment gateway is irrelevant to the behaviour you are testing (product lookup). Which double should you use for the gateway?

- A) Spy — to record if `charge()` was called
- B) Stub — to return `true`
- C) Null Object — silent, satisfies the interface, returns the zero value
- D) Fake — to implement a real in-memory payment processor

---

**Q4.** What is the PRIMARY advantage of using anonymous class test doubles over PHPUnit's `createMock()`?

- A) Anonymous classes are faster to run.
- B) Anonymous classes implement the full interface contract enforced by PHP, are defined where they are used, have no magic, and require no framework.
- C) Anonymous classes automatically verify that methods were called.
- D) Anonymous classes allow you to use `assertSame()` on the double itself.

---

**Q5.** You have a `SpyMailer` with a public `$sent` array. After calling `$service->placeOrder(...)`, you write:

```php
$this->assertSame('alice@example.com', $this->spyMailer->sent[0]['to']);
```

This assertion fails. Which of the following is the most likely cause?

- A) `assertSame` does not work on array elements.
- B) The `$sent` property must be `private` for PHPUnit to read it.
- C) `placeOrder()` did not send any email — `$sent` is empty, so index `0` does not exist.
- D) The spy must be declared `static` to be read after a method call.

---

**Q6.** A stub returns `false` from `charge()`. A throwing stub throws `\RuntimeException` from `charge()`. What is the difference in how `OrderService` should handle each?

- A) Both should cause `placeOrder()` to return `['success' => false]`.
- B) `false` means "declined" — a recoverable business outcome; the exception means "gateway is down" — an unrecoverable infrastructure failure.
- C) There is no difference; `false` and an exception are equivalent failure modes.
- D) The throwing stub should be caught inside `placeOrder()` and returned as `['error' => 'gateway error']`.

---

**Q7.** Which of the following should NOT be replaced with a test double?

- A) A database repository that makes real SQL queries
- B) An email service that sends real SMTP messages
- C) A `Money` value object that contains no I/O
- D) A third-party payment gateway that makes HTTP calls

---

**Q8.** You define a fake `ProductRepositoryInterface` that stores an array of products in memory. What makes this a **fake** rather than a **stub**?

- A) It returns a non-null value.
- B) It has real internal logic — it actually stores and retrieves data — rather than just returning a fixed predetermined value.
- C) It is defined as a named class rather than an anonymous class.
- D) It records every call made to it.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A Null Object double should record calls so you can assert the dependency was not called. | |
| 10 | A spy can combine spy behaviour (recording) with stub behaviour (returning a controlled value) in the same anonymous class. | |
| 11 | A test that verifies a side effect did NOT occur should use a spy (asserting the recorded array is empty). | |
| 12 | If `setUp()` creates the service with a happy-path stub gateway, a test that needs the gateway to fail must create a new `OrderService` instance with an inline failing stub. | |
| 13 | The public `$sent` property on a spy anonymous class is a design mistake — it should be private with a getter. | |
| 14 | A Fake differs from a stub in that it has real behaviour, not just hardcoded return values. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why the unit test contract says "all dependencies are replaced by test doubles." What problem does this solve?

*Your answer:*

---

**Q16.** A developer writes this stub:

```php
$stubGateway = new class implements PaymentGatewayInterface {
    public function charge(int $amountCents, string $token): bool {
        return true;
    }
};
```

They then write:

```php
public function testPaymentGatewayIsCalledWithCorrectAmount(): void
{
    $this->service->placeOrder(1, 2, 'tok', 'alice@example.com');
    // How do they assert the gateway was called with 59998?
    // ...
}
```

The stub does not record calls. What double type should replace it, and what does the replacement look like?

*Your answer:*

---

**Q17.** Describe the SPY vs STUB distinction with a concrete example from `OrderService`. When do you need a stub? When do you need a spy?

*Your answer:*

---

## Section D — Code Reading

**Q18.** What is wrong with the following test? Identify every problem.

```php
public function testPlaceOrderSendsEmailOnSuccess(): void
{
    $spyMailer = new class implements MailerInterface {
        private array $sent = [];   // private

        public function send(string $to, string $subject, string $body): bool {
            $this->sent[] = compact('to', 'subject', 'body');
            return true;
        }
    };

    $this->service = new OrderService(
        $this->fakeProducts,
        $this->stubGateway,
        $spyMailer
    );

    $this->service->placeOrder(1, 1, 'tok', 'alice@example.com');

    $this->assertCount(1, $spyMailer->sent);   // reads the spy
    $this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
}
```

*Your answer:*

---

**Q19.** Trace through the following test and explain what it verifies. Does it pass? Why or why not?

```php
public function testNoSideEffectsOnDeclinedPayment(): void
{
    $decliningGateway = new class implements PaymentGatewayInterface {
        public function charge(int $amountCents, string $token): bool { return false; }
    };

    $spyMailer = new class implements MailerInterface {
        public array $sent = [];
        public function send(string $to, string $subject, string $body): bool {
            $this->sent[] = compact('to', 'subject', 'body');
            return true;
        }
    };

    $service = new OrderService($this->fakeProducts, $decliningGateway, $spyMailer);

    $result = $service->placeOrder(1, 1, 'tok_fail', 'alice@example.com');

    $this->assertFalse($result['success']);
    $this->assertNull($result['order_id']);
    $this->assertEmpty($spyMailer->sent);
}
```

*Your answer:*

---

**Q20.** A teammate proposes this reusable helper for Null Object creation:

```php
private function nullMailer(): MailerInterface
{
    return new class implements MailerInterface {
        public function send(string $to, string $subject, string $body): bool { return true; }
    };
}
```

They argue this Null Object should be used in the test below. Is that correct? If not, what should be used instead and why?

```php
public function testPlaceOrderSendsEmailToCustomer(): void
{
    $service = new OrderService(
        $this->fakeProducts,
        $this->stubGateway,
        $this->nullMailer()  // ← is this correct?
    );

    $service->placeOrder(1, 1, 'tok', 'alice@example.com');

    // Can we assert the recipient here?
    $this->assertSame('alice@example.com', /* what goes here? */);
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
| 1 | **C** | A unit test tests one class in isolation with all dependencies replaced. Speed is a consequence, not the definition. Testing "one method" is too narrow — a single method may have many tests. |
| 2 | **C** | A spy records calls. A stub returns fixed values. A fake has real logic. A Null Object does nothing. |
| 3 | **C** | If the gateway is irrelevant to the behaviour under test, a Null Object is the right choice — it satisfies the interface silently. A stub returning `true` would also work, but a Null Object communicates intent more clearly ("I don't care about this"). |
| 4 | **B** | PHP enforces the interface on anonymous classes at compile time. The behaviour is explicit and readable. No framework is required. |
| 5 | **C** | The most likely cause is that `placeOrder()` did not reach the email step — perhaps it threw an exception or returned early — leaving `$sent` empty. `assertSame` works on array elements; `private` does not prevent PHPUnit from reading it (the test can read the property directly since PHP anonymous classes follow the same visibility rules as named classes). |
| 6 | **B** | `false` = the card was declined — a normal, handled business outcome. An exception = the gateway itself failed — an unrecoverable infrastructure problem. `OrderService` returns a failure result for `false` and lets the exception propagate. |
| 7 | **C** | A `Money` value object is pure in-memory computation with no I/O, no network, no disk. It is lightweight and deterministic — use the real thing. |
| 8 | **B** | A fake has real internal logic — the in-memory store actually stores and retrieves data. A stub just returns a hardcoded value regardless of input. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | A Null Object does NOT record calls. If you need to verify a dependency was not called, use a spy (assert its recording array is empty). A Null Object's entire job is to be silent. |
| 10 | **T** | An anonymous class can have a public `$sent` array (spy behaviour) and also return a controlled value from its method (stub behaviour). Both patterns coexist in the same class. |
| 11 | **T** | If you expect a dependency NOT to be called, create a spy and assert `assertEmpty($spy->sent)` after running the code. This is the standard pattern for "no side effect" verification. |
| 12 | **T** | The service is wired once in `setUp()`. To test with a different gateway, you must construct a new `OrderService` with the inline failing stub. You cannot swap a dependency mid-test on an already-constructed object (without a setter — which `OrderService` does not have). |
| 13 | **F** | The public property is intentional. The test must read the spy's recording. Making it private would require a getter, which adds boilerplate with no benefit. Test doubles are not production code — simplicity is preferred. |
| 14 | **T** | A fake has real working behaviour (e.g. an in-memory store that actually stores and retrieves). A stub just returns a predetermined fixed value — no real logic. |

## Section C

**Q15 — Model answer:**
Unit tests replace all dependencies with doubles to isolate the class under test completely. If a real database, network, or email service is used, a test failure might be caused by the infrastructure rather than the class — making failures misleading, slow, and environment-dependent. Test doubles make every failure point directly to the class under test.

**Q16 — Model answer:**
A stub only returns a value — it does not record calls. To assert that `charge()` was called with the correct amount, you need a **spy**. Replace the stub with:

```php
$spyGateway = new class implements PaymentGatewayInterface {
    public array $calls = [];

    public function charge(int $amountCents, string $token): bool {
        $this->calls[] = compact('amountCents', 'token');
        return true;
    }
};
```

Then assert:
```php
$this->assertCount(1, $spyGateway->calls);
$this->assertSame(59998, $spyGateway->calls[0]['amountCents']); // 29999 × 2
```

**Q17 — Model answer:**
You need a **stub** when you want to control what a dependency *returns* to the class under test, so you can verify the class's reaction. For `OrderService`, a stub gateway that returns `false` lets you test how `placeOrder()` reacts to a declined card — the behaviour under test is `OrderService`'s return value.

You need a **spy** when you want to verify a side effect — what the class under test *did* to the dependency. For `OrderService`, a spy mailer lets you verify that the confirmation email was sent to the right address with the right subject. You cannot verify this by inspecting the return value of `placeOrder()` alone.

## Section D

**Q18 — Answer:**
Two problems:

1. **`$sent` is `private`**: The test tries to access `$spyMailer->sent` from outside the class, but `private` prevents this. The property must be `public` for the test to read it. This would cause a PHP fatal error or a PHPUnit error.

2. **The service is re-assigned**: `$this->service` is overwritten mid-test with a new service that uses the local `$spyMailer`. This works, but now `$this->service` has been changed for any subsequent test that relies on the `setUp()` value — except that since `setUp()` runs before every test this is actually fine, but it is still confusing to reassign `$this->service` inside a test. Better practice: use a local `$service` variable inside the test.

**Q19 — Answer:**
The test passes and verifies three related behaviours of the payment declined path:

1. `assertFalse($result['success'])` — `OrderService` returns a failure result when the gateway returns `false`
2. `assertNull($result['order_id'])` — no order ID is assigned on failure
3. `assertEmpty($spyMailer->sent)` — the mailer spy confirms no email was sent before returning the failure result

The test constructs a local `$service` with the inline failing stub and the local spy. This is correct — `setUp()` builds the happy-path service, and this test overrides the gateway with a failing stub. The spy correctly captures zero calls because `OrderService` returns early after the declined payment, before reaching the `$this->mailer->send()` call.

**Q20 — Answer:**
No, the Null Object is NOT correct here. The test is trying to assert that the email was sent to the right recipient — that is a side effect assertion that requires a **spy**, not a Null Object.

A Null Object's `send()` discards the call silently. There is nothing to assert on. The test as written cannot complete the assertion at the end.

The fix: replace `nullMailer()` with a spy:

```php
public function testPlaceOrderSendsEmailToCustomer(): void
{
    $spyMailer = new class implements MailerInterface {
        public array $sent = [];
        public function send(string $to, string $subject, string $body): bool {
            $this->sent[] = compact('to', 'subject', 'body');
            return true;
        }
    };

    $service = new OrderService($this->fakeProducts, $this->stubGateway, $spyMailer);

    $service->placeOrder(1, 1, 'tok', 'alice@example.com');

    $this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
}
```

**Rule:** If you need to assert something about a dependency, use a spy. If you do not care about it, use a Null Object.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 5.3 — strong grasp of test double selection. |
| 14–17 | Re-read Sections 2 and 7 of the README for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |