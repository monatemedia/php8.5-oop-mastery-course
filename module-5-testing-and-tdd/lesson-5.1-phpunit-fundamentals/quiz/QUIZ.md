# Quiz — Lesson 5.1: PHPUnit Fundamentals
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** Which of the following is the correct way to mark a method as a test in PHPUnit?

- A) The method must be `private` and named `test*`.
- B) The method must be `public` and named `test*`, OR be `public` and carry the `#[Test]` attribute.
- C) The method must extend `TestCase` directly.
- D) The method must return `bool`.

---

**Q2.** What is the difference between `assertSame()` and `assertEquals()`?

- A) `assertSame()` compares strings only; `assertEquals()` compares all types.
- B) `assertSame()` uses `===` (strict, type + value); `assertEquals()` uses `==` (loose, type coercion allowed).
- C) They are identical — one is an alias for the other.
- D) `assertSame()` checks object identity; `assertEquals()` checks that two objects have the same class name.

---

**Q3.** You want to assert that calling `new Money(-1, 'ZAR')` throws `\InvalidArgumentException`. Which is the correct implementation?

- A)
```php
try {
    new Money(-1, 'ZAR');
} catch (\InvalidArgumentException $e) {
    $this->assertTrue(true);
}
```
- B)
```php
new Money(-1, 'ZAR');
$this->expectException(\InvalidArgumentException::class);
```
- C)
```php
$this->expectException(\InvalidArgumentException::class);
new Money(-1, 'ZAR');
```
- D)
```php
$this->assertThrows(\InvalidArgumentException::class, fn() => new Money(-1, 'ZAR'));
```

---

**Q4.** `setUp()` in a PHPUnit test class runs:

- A) Once before the entire test class.
- B) Once after the entire test class.
- C) Before every individual test method.
- D) After every individual test method.

---

**Q5.** You have three test methods. Test B leaves a static property dirty. Test C then fails because of that dirty state. Test A passes in isolation. What is this called, and what is the fix?

- A) A race condition. Fix: use `$this->markTestSkipped()` on Test C.
- B) An order-dependent test failure caused by shared state. Fix: create fresh objects in `setUp()` rather than sharing state between tests.
- C) A fixture error. Fix: use `setUpBeforeClass()` to reset the static property.
- D) A data provider conflict. Fix: add `#[Test]` to every affected method.

---

**Q6.** `assertCount(3, $result)` passes when:

- A) `$result` has exactly 3 elements (array or `Countable`).
- B) `$result` is the integer 3.
- C) `$result === 3`.
- D) `strlen($result) === 3`.

---

**Q7.** A test has no assertions and the tested code does not throw. What does PHPUnit report?

- A) Pass — a test with no assertions is implicitly green.
- B) Risky — PHPUnit warns that the test did not assert anything (by default).
- C) Error — PHPUnit requires at least one assertion per test.
- D) Skip — PHPUnit ignores tests without assertions.

---

**Q8.** You want to run the same test with multiple inputs using a data provider. The data provider method must be:

- A) `private` and named `*Provider`.
- B) `public static` and return an array of arrays.
- C) `protected` and return a `Generator`.
- D) `public` instance method returning an associative array.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | `tearDown()` still runs if a test method throws an unexpected exception. | |
| 10 | `expectException()` must be called after the code that throws. | |
| 11 | `assertSame($a, $b)` on two different object instances with identical property values returns true. | |
| 12 | PHPUnit will discover a test method named `checkBalance()` without the `test` prefix or `#[Test]` attribute. | |
| 13 | `assertEqualsWithDelta(1.5, $result, 0.01)` passes when `$result` is `1.505`. | |
| 14 | Each test method should ideally test one behaviour — not unrelated behaviours bundled together. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why `setUp()` should create fresh objects rather than reusing objects created once in `setUpBeforeClass()`. What problem does fresh creation prevent?

*Your answer:*

---

**Q16.** A colleague writes:

```php
public function testPlaceOrder(): void
{
    $service = new OrderService(new FakeDb(), new SpyMailer(), new NullLogger());
    $result  = $service->placeOrder(1, 'alice@example.com');
    $this->assertTrue($result['success']);
    $this->assertCount(1, $spyMailer->sent);   // ← notice: undefined variable
    $this->assertSame('confirmed', $result['status']);
    $this->assertGreaterThan(0, $result['order_id']);
}
```

Identify two problems and explain how to fix each.

*Your answer:*

---

**Q17.** Why is the following exception test unreliable, and how should it be rewritten?

```php
public function testNegativeAmountThrows(): void
{
    try {
        new Money(-1, 'ZAR');
        $this->fail('Expected InvalidArgumentException');
    } catch (\Exception $e) {
        $this->assertStringContainsString('non-negative', $e->getMessage());
    }
}
```

*Your answer:*

---

## Section D — Code Reading

**Q18.** What does the following test output? Will it pass or fail? Explain.

```php
public function testAdditionIsCommutative(): void
{
    $a = new Money(100, 'ZAR');
    $b = new Money(200, 'ZAR');

    $this->assertSame(
        $a->add($b)->amountCents,
        $b->add($a)->amountCents
    );
}
```

*Your answer:*

---

**Q19.** Identify every problem in the following test class.

```php
class OrderTest extends TestCase
{
    public static Order $order;

    public static function setUpBeforeClass(): void
    {
        self::$order = new Order('alice@example.com');
    }

    public function test_order_is_pending(): void
    {
        $this->assertSame('pending', self::$order->getStatus());
    }

    public function test_confirm_changes_status(): void
    {
        self::$order->confirm();
        $this->assertSame('confirmed', self::$order->getStatus());
    }

    public function test_order_is_still_pending(): void
    {
        // Assumes status is still 'pending' — but test_confirm_changes_status
        // may have run first and changed it to 'confirmed'
        $this->assertSame('pending', self::$order->getStatus());
    }

    private function test_cancel_changes_status(): void
    {
        self::$order->cancel();
        $this->assertSame('cancelled', self::$order->getStatus());
    }
}
```

*Your answer:*

---

**Q20.** What will the following test output? Trace through the data provider and explain which sub-tests pass or fail.

```php
class MoneyFormatTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCases')]
    public function testFormat(int $cents, string $currency, string $expected): void
    {
        $money = new Money($cents, $currency);
        $this->assertSame($expected, $money->format());
    }

    public static function formatCases(): array
    {
        return [
            'zero'        => [0,     'ZAR', 'ZAR 0.00'],
            'R299.99'     => [29999, 'ZAR', 'ZAR 299.99'],
            'wrong label' => [100,   'USD', 'ZAR 1.00'],   // intentional error
        ];
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
| 1 | **B** | PHPUnit discovers public methods named `test*` automatically. The `#[Test]` attribute (PHP 8.0+) allows any public method name. Private or protected test methods are never run. |
| 2 | **B** | `assertSame()` uses `===` — same type and same value. `assertEquals()` uses `==` — type coercion applies. Prefer `assertSame()` to catch type bugs like `1 !== '1'`. |
| 3 | **C** | `expectException()` must come BEFORE the throwing call. PHPUnit registers a handler that intercepts the exception; if the exception is thrown first, PHPUnit never sees it. |
| 4 | **C** | `setUp()` runs before every individual test method. This ensures each test starts with a clean, consistent state. |
| 5 | **B** | Order-dependent failure caused by shared mutable state. The fix is `setUp()` creating a fresh object before each test method. |
| 6 | **A** | `assertCount(N, $collection)` passes when `count($collection) === N`. The collection can be an array or a `Countable` object. |
| 7 | **B** | PHPUnit marks tests with zero assertions as "Risky" by default. This is configurable in `phpunit.xml` via `beStrictAboutTestsThatDoNotTestAnything`. |
| 8 | **B** | Data providers must be `public static` methods returning an array of arrays. Each inner array is one test invocation's argument list. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | `tearDown()` always runs after a test, even if the test fails or throws unexpectedly. This ensures cleanup code always executes. |
| 10 | **F** | `expectException()` must be called BEFORE the throwing code. Calling it after means the exception has already propagated and PHPUnit cannot intercept it. |
| 11 | **F** | `assertSame()` on objects checks **identity** (same object reference in memory, `===`). Two separate instances with identical property values are NOT the same. Use `assertEquals()` for property equality. |
| 12 | **F** | PHPUnit only discovers methods prefixed with `test` or annotated with `#[Test]`. `checkBalance()` is ignored. |
| 13 | **T** | `assertEqualsWithDelta(1.5, 1.505, 0.01)` passes because `|1.5 - 1.505| = 0.005 < 0.01`. |
| 14 | **T** | Each test should verify one behaviour. Bundling unrelated assertions makes failures harder to diagnose and makes tests harder to name meaningfully. |

## Section C

**Q15 — Model answer:**
`setUp()` creates fresh objects before each test to prevent shared state from leaking between tests. If test A modifies an object and test B depends on that same object being in its original state, B will fail whenever A runs first — an order-dependent failure. Fresh objects ensure each test is fully isolated and can run in any order with identical results.

**Q16 — Model answer:**
Problem 1: **Undefined variable `$spyMailer`**. The spy is created inside the test method but referenced as `$spyMailer` without the `$` being in scope at that point — in this case the `SpyMailer` was constructed inline anonymously and the variable was never assigned to a named reference. Fix: assign the spy to a variable: `$spy = new SpyMailer(); $service = new OrderService(new FakeDb(), $spy, ...);` and then assert `$this->assertCount(1, $spy->sent)`.

Problem 2: **The test covers multiple unrelated behaviours** — success return value, mailer call count, status string, and order ID generation. If any single assertion fails, it is not immediately clear which behaviour is broken. Fix: split into `testPlaceOrderReturnsSuccess()`, `testPlaceOrderSendsOneEmail()`, `testPlaceOrderReturnsConfirmedStatus()`, and `testPlaceOrderReturnsNonZeroOrderId()`.

**Q17 — Model answer:**
The test catches `\Exception` — any exception — instead of `\InvalidArgumentException` specifically. If the code throws a `\RuntimeException` or any other exception for an unrelated reason, the catch block still runs and the test passes silently, hiding the bug. The message assertion inside the catch is also only reached if an exception IS thrown; if the constructor is changed to silently accept -1 (no throw at all), `$this->fail()` catches it, but that path is non-obvious.

The reliable rewrite:
```php
public function testNegativeAmountThrows(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('non-negative');
    new Money(-1, 'ZAR');
}
```
This is unambiguous: fails if no exception, fails if the wrong exception type, fails if the message does not contain 'non-negative'.

## Section D

**Q18 — Answer:**
The test **passes**. Both `$a->add($b)` and `$b->add($a)` produce a new `Money` with `amountCents = 300`. `assertSame(300, 300)` is `true`. Addition is commutative for this value object — the amounts are equal regardless of operand order.

**Q19 — Answer:**
Four problems:

1. **Shared mutable state**: `self::$order` is set once in `setUpBeforeClass()` and mutated across tests. `test_confirm_changes_status()` calls `confirm()`, permanently changing the shared order's status. Any test that runs after it and expects `'pending'` will fail.

2. **Order-dependent failure**: `test_order_is_still_pending()` will fail if `test_confirm_changes_status()` runs before it. PHPUnit does not guarantee method execution order.

3. **Private test method**: `test_cancel_changes_status()` is `private`. PHPUnit never discovers or runs it. Any coverage it was intended to provide is silently absent.

4. **No isolation**: The fix is to replace `public static Order $order` and `setUpBeforeClass()` with a `protected function setUp(): void` that creates a fresh `Order` instance via `$this->order = new Order('alice@example.com')` before each test.

**Q20 — Answer:**
Two sub-tests pass, one fails:

- `zero` → `new Money(0, 'ZAR')->format()` returns `'ZAR 0.00'` === `'ZAR 0.00'` → **PASS**
- `R299.99` → `new Money(29999, 'ZAR')->format()` returns `'ZAR 299.99'` === `'ZAR 299.99'` → **PASS**
- `wrong label` → `new Money(100, 'USD')->format()` returns `'USD 1.00'` but expected `'ZAR 1.00'` → **FAIL**

PHPUnit output:
```
MoneyFormatTest::testFormat with data set "wrong label" (#2)
Failed asserting that 'USD 1.00' is identical to 'ZAR 1.00'.
```
The data set label `'wrong label'` appears in the failure message, making it easy to identify which case failed.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 5.2 — strong PHPUnit foundation. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |