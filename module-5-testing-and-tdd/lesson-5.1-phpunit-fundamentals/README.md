# Lesson 5.1 — PHPUnit Fundamentals
> **Module 5: Automated Testing & TDD** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-5.1-phpunit-fundamentals/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-first-test.php                  ← Anatomy of a test class
│   ├── 02-assertions.php                  ← Core assertions demonstrated
│   ├── 03-exception-testing.php           ← expectException + expectExceptionMessage
│   └── 04-setup-and-teardown.php          ← setUp / tearDown lifecycle
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── MoneyTest.php
│   └── solution/
│       └── MoneyTest.php
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Install PHPUnit (instructions below — one-time setup).
2. Read this README fully before touching any test file.
3. Run each example and read the output.
4. Complete the challenge.
5. Take the quiz cold.

---

## 0 — Installing PHPUnit

PHPUnit is installed via Composer as a dev dependency. Run this once from your project root:

```bash
composer require --dev phpunit/phpunit
```

Verify:
```bash
./vendor/bin/phpunit --version
# PHPUnit 11.x.x by Sebastian Bergmann and contributors.
```

Add a `phpunit.xml` configuration file to the project root:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="PHP OOP Mastery Course">
            <directory>module-5-testing-and-tdd</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Running tests:
```bash
# Run the entire test suite
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/challenge/solution/MoneyTest.php

# Run a single test method
./vendor/bin/phpunit --filter testAddReturnsCorrectSum

# Run with verbose output
./vendor/bin/phpunit --testdox
```

---

## 1 — The Anatomy of a PHPUnit Test

Every PHPUnit test class follows the same structure:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase          // Must extend TestCase
{
    private OrderService $service;              // The "subject under test"
    private SpyMailer    $spyMailer;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    protected function setUp(): void            // Runs BEFORE each test method
    {
        $this->spyMailer = new SpyMailer();
        $this->service   = new OrderService(
            new FakeDatabase(),
            $this->spyMailer,
            new NullLogger()
        );
    }

    protected function tearDown(): void         // Runs AFTER each test method
    {
        // Clean up any state, close connections, delete temp files
    }

    // ── Test methods ──────────────────────────────────────────────────────────

    public function testPlaceOrderReturnsSuccessForValidProduct(): void
    {
        $result = $this->service->placeOrder(productId: 1, email: 'alice@example.com');

        $this->assertTrue($result['success']);
    }

    public function testPlaceOrderSendsConfirmationEmail(): void
    {
        $this->service->placeOrder(productId: 1, email: 'alice@example.com');

        $this->assertCount(1, $this->spyMailer->sent);
        $this->assertSame('alice@example.com', $this->spyMailer->sent[0]['to']);
    }

    #[Test]                                     // PHP 8.0+ attribute alternative
    public function placeOrderThrowsForUnknownProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->placeOrder(productId: 999, email: 'alice@example.com');
    }
}
```

**Rules:**
- The class must `extend TestCase`
- Test methods must be `public`
- Test methods are named `test*` OR carry the `#[Test]` attribute
- Each test method should test **one behaviour**
- `setUp()` runs before every test method — use it to create fresh objects
- `tearDown()` runs after every test method — use it to clean up

---

## 2 — Naming Test Methods

Good test names describe the behaviour being tested, not the method being called:

```
❌  testPlaceOrder()
❌  testPlaceOrderWorks()
✅  testPlaceOrderReturnsSuccessForValidProduct()
✅  testPlaceOrderThrowsInvalidArgumentExceptionForUnknownProduct()
✅  testPlaceOrderSendsOneConfirmationEmailOnSuccess()
```

The pattern: `test[Subject][Behaviour][Context]`

With the `#[Test]` attribute you can use natural language and underscores:
```php
#[Test]
public function place_order_returns_success_for_valid_product(): void { ... }
```

---

## 3 — Core Assertions

PHPUnit provides dozens of assertions. These are the ones you will use in 95% of tests:

### Equality

```php
// assertSame: strict equality (===) — same type AND same value
$this->assertSame(42, $result);           // pass: int 42 === int 42
$this->assertSame('hello', $result);      // fail: int 0 !== string 'hello'

// assertEquals: loose equality (==) — type coercion allowed
$this->assertEquals(42, '42');            // pass: 42 == '42'

// Prefer assertSame for most cases — it catches type bugs
```

### Booleans and null

```php
$this->assertTrue($result);
$this->assertFalse($result);
$this->assertNull($result);
$this->assertNotNull($result);
```

### Counts and types

```php
$this->assertCount(3, $collection);        // count($collection) === 3
$this->assertEmpty($collection);           // empty($collection) === true
$this->assertNotEmpty($collection);

$this->assertInstanceOf(Order::class, $result);
$this->assertIsArray($result);
$this->assertIsString($result);
$this->assertIsInt($result);
```

### Strings

```php
$this->assertStringContainsString('hello', $subject);
$this->assertStringStartsWith('http', $url);
$this->assertStringEndsWith('.json', $filename);
$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
```

### Arrays

```php
$this->assertArrayHasKey('id', $result);
$this->assertContains('alice@example.com', $emails);
$this->assertSame(['a', 'b', 'c'], $result);
```

### Numeric

```php
$this->assertGreaterThan(0, $price);
$this->assertGreaterThanOrEqual(1, $quantity);
$this->assertLessThan(100, $discount);
$this->assertEqualsWithDelta(29.99, $price, delta: 0.001); // floating point
```

---

## 4 — Exception Testing

Test that a method throws the right exception:

```php
public function testThrowsForNegativeAmount(): void
{
    // Declare BEFORE the code that throws
    $this->expectException(\InvalidArgumentException::class);

    $this->money->subtract(-50);   // This line must throw
}

public function testExceptionMessageContainsAmount(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Amount must be positive');

    $this->money->subtract(-50);
}

public function testExceptionCode(): void
{
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionCode(404);

    $this->repository->findOrFail(999);
}
```

**Common mistake:** declaring `expectException()` after the throwing call. It must come before.

---

## 5 — setUp() and tearDown() Lifecycle

```php
class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private SpyMailer    $spy;

    protected function setUp(): void
    {
        // Called before EVERY test method
        // Create fresh objects here — ensures test isolation
        $this->spy     = new SpyMailer();
        $this->service = new OrderService(
            new FakeDatabase(),
            $this->spy,
            new NullLogger()
        );
    }

    protected function tearDown(): void
    {
        // Called after EVERY test method
        // Use for: closing files, deleting temp data, resetting singletons
        // For in-memory objects: usually not needed (PHP garbage-collects them)
    }

    // setUpBeforeClass() / tearDownAfterClass() run once per CLASS (static methods)
    // Use sparingly — shared state between tests causes order-dependent failures
    public static function setUpBeforeClass(): void { /* ... */ }
    public static function tearDownAfterClass(): void { /* ... */ }
}
```

**The isolation rule:** every test method must be able to run in any order and produce the same result. `setUp()` enforcing this by creating fresh objects before each test is the key mechanism.

---

## 6 — Test Output: Green, Yellow, Red

```
PHPUnit 11.x.x

.....F..E.S..

Tests: 13, Assertions: 24, Failures: 1, Errors: 1, Skipped: 1.
```

| Symbol | Meaning |
|--------|---------|
| `.`    | Test passed |
| `F`    | Failure — assertion failed (expected X, got Y) |
| `E`    | Error — an exception was thrown that was not expected |
| `S`    | Skipped — test was marked `$this->markTestSkipped()` |
| `I`    | Incomplete — test was marked `$this->markTestIncomplete()` |

With `--testdox`:
```
OrderService
 ✔ Place order returns success for valid product
 ✔ Place order sends one confirmation email on success
 ✘ Place order throws invalid argument exception for unknown product
```

---

## 7 — Data Providers

When you want to run the same test with multiple inputs:

```php
class MoneyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAmountProvider')]
    public function testThrowsForInvalidAmount(int $amount): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Money($amount, 'ZAR');
    }

    public static function invalidAmountProvider(): array
    {
        return [
            'negative amount'  => [-1],
            'very negative'    => [-999],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('additionProvider')]
    public function testAddition(int $a, int $b, int $expected): void
    {
        $money  = new Money($a, 'ZAR');
        $result = $money->add(new Money($b, 'ZAR'));
        $this->assertSame($expected, $result->amountCents());
    }

    public static function additionProvider(): array
    {
        return [
            'zero + zero'         => [0,    0,    0],
            'positive + positive' => [100,  200,  300],
            'large amounts'       => [9999, 1,    10000],
        ];
    }
}
```

Data providers must be `public static` methods. Each array entry is one test run.

---

## 8 — What NOT to Test

Testing everything equally is as bad as testing nothing. Focus on behaviour:

```
✅ Test: return values of public methods
✅ Test: exceptions thrown for invalid inputs
✅ Test: side effects on collaborators (via spies)
✅ Test: edge cases (empty input, zero, maximum value)

❌ Do not test: private methods (test them via the public API)
❌ Do not test: framework or library internals (PHPUnit, PHP-DI, Slim)
❌ Do not test: getters/setters that contain no logic
❌ Do not test: how many constructor parameters a class has (Rule 2)
❌ Do not test: which concrete class was injected
```

The rule of thumb: if refactoring the class *internals* without changing *observable behaviour* breaks your tests, the tests are too coupled to implementation.

---

## 9 — The Money Value Object (Challenge Subject)

The challenge tests a `Money` value object — a classic PHPUnit target because it has clear inputs, clear outputs, and raises exceptions for invalid inputs.

```php
readonly class Money
{
    public function __construct(
        public int    $amountCents,
        public string $currency
    ) {
        if ($amountCents < 0) {
            throw new \InvalidArgumentException(
                "Amount must be non-negative, got {$amountCents}"
            );
        }
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException(
                "Currency must be a 3-letter ISO code, got '{$currency}'"
            );
        }
    }

    public function add(Money $other): static
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Cannot add {$this->currency} and {$other->currency}"
            );
        }
        return new static($this->amountCents + $other->amountCents, $this->currency);
    }

    public function subtract(Money $other): static
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Cannot subtract {$this->currency} and {$other->currency}"
            );
        }
        if ($other->amountCents > $this->amountCents) {
            throw new \InvalidArgumentException(
                "Cannot subtract {$other->amountCents} from {$this->amountCents}: result would be negative"
            );
        }
        return new static($this->amountCents - $other->amountCents, $this->currency);
    }

    public function multiplyBy(float $factor): static
    {
        if ($factor < 0) {
            throw new \InvalidArgumentException(
                "Factor must be non-negative, got {$factor}"
            );
        }
        return new static((int) round($this->amountCents * $factor), $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountCents > $other->amountCents;
    }

    public function equals(Money $other): bool
    {
        return $this->amountCents === $other->amountCents
            && $this->currency    === $other->currency;
    }

    public function format(): string
    {
        return $this->currency . ' ' . number_format($this->amountCents / 100, 2);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
```

---

## 10 — Quick Reference

```php
// Test class skeleton
class MyTest extends TestCase {
    protected function setUp(): void    { /* before each test */ }
    protected function tearDown(): void { /* after each test */ }

    public function testSomeBehaviour(): void {
        // Arrange
        $subject = new MyClass();

        // Act
        $result = $subject->doSomething();

        // Assert
        $this->assertSame('expected', $result);
    }
}

// Assertions — most used
$this->assertSame($expected, $actual);
$this->assertEquals($expected, $actual);
$this->assertTrue($condition);
$this->assertFalse($condition);
$this->assertNull($value);
$this->assertNotNull($value);
$this->assertCount(3, $collection);
$this->assertInstanceOf(SomeClass::class, $object);
$this->assertStringContainsString('needle', $haystack);
$this->assertArrayHasKey('key', $array);
$this->assertGreaterThan(0, $number);
$this->assertEqualsWithDelta(29.99, $float, 0.001);

// Exception testing
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('some message fragment');

// Running
./vendor/bin/phpunit
./vendor/bin/phpunit --testdox
./vendor/bin/phpunit --filter testMethodName
./vendor/bin/phpunit path/to/SomeTest.php
```

---

## ✅ Lesson Checklist

- [ ] Run `composer require --dev phpunit/phpunit` and verify `./vendor/bin/phpunit --version`
- [ ] Add `phpunit.xml` to the project root
- [ ] Read this README fully — especially Sections 3 (assertions) and 5 (lifecycle)
- [ ] Run and study `examples/01-first-test.php` (as a test file via PHPUnit)
- [ ] Run and study `examples/02-assertions.php`
- [ ] Run and study `examples/03-exception-testing.php`
- [ ] Run and study `examples/04-setup-and-teardown.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter/MoneyTest.php`
- [ ] Check your work against `challenge/solution/MoneyTest.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **5.2 — Unit Testing with Fakes and Stubs** — test one class at a time by replacing its dependencies with test doubles.*