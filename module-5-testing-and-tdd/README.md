# Module 5 — Automated Testing & TDD
> **PHP 8.5 OOP Mastery Course**
> **Folder:** `module-5-testing-and-tdd/`

---

## Why This Module Exists

Everything you learned in Modules 1–4 was leading here. You now have:

- **Module 1** — OOP tools that produce clean, decoupled class designs
- **Module 2** — A type system that catches category errors at the boundary
- **Module 3** — Dependency Injection that makes every class independently testable
- **Module 4** — A container that wires your entire application graph from a single entry point

Module 5 is where you **prove** that the system works. Not by running the application and clicking around, but by writing automated assertions that verify each piece of behaviour in isolation — without a browser, without a database, without network access.

> **Composition over Inheritance (Lesson 1.4) + Dependency Injection (Module 3) are the prerequisites for effective unit testing.** A class that creates its own dependencies cannot be unit-tested. A deep inheritance tree cannot be tested in isolation. You have already fixed both of those problems.

---

## Module Goal

By the end of this module you will be able to:

1. Write PHPUnit tests that verify individual class behaviour without any real infrastructure
2. Use Test-Driven Development (TDD) to let failing tests drive your class design
3. Write integration tests that spin up a real container and verify the wired system
4. Recognise and avoid the "test layout, not behaviour" anti-pattern
5. Know what to mock, what to stub, what to fake, and when to use each

---

## 🗺️ Module Roadmap

```
[Lesson 5.0: Why Testing Requires DI]
           ↓
[Lesson 5.1: PHPUnit Fundamentals]
           ↓
[Lesson 5.2: Unit Testing with Fakes and Stubs]
           ↓
[Lesson 5.3: Test-Driven Development — Red, Green, Refactor]
           ↓
[Lesson 5.4: Integration Testing with a Real Container]
           ↓
[Lesson 5.5: Testing Behaviours, Not Layouts]
```

---

## Lesson 5.0 — Why Testing Requires DI

> **The payoff lesson** — connects everything from Modules 1–4 to the testing world.

### Topics

- Why tightly coupled code cannot be unit tested (the same three costs from Lesson 3.1, now made visceral)
- How constructor injection makes every class testable in isolation
- The four test double types: **Fake** (lightweight implementation), **Stub** (returns fixed values), **Spy** (records calls), **Mock** (asserts call expectations)
- Why anonymous classes (Lesson 2.4) are the ideal test double for PHP
- The test environment as a composition root — testing is just wiring with fakes

### Key concepts
- **Test double** — any object used in place of a real dependency in a test
- **Unit test** — tests one class in isolation; all dependencies are test doubles
- **Integration test** — tests multiple real classes wired together
- **System test** — tests the whole application end-to-end

### Examples
```
examples/
  01-why-tight-coupling-breaks-tests.php   ← class that creates its own deps cannot be tested
  02-di-makes-testing-possible.php         ← same class, injected, fully testable
  03-the-four-double-types.php             ← Fake, Stub, Spy, Mock — side by side
```

---

## Lesson 5.1 — PHPUnit Fundamentals

> **Install PHPUnit via Composer. Learn the anatomy of a test.**

### Topics

- Installing PHPUnit in your Herd project: `composer require --dev phpunit/phpunit`
- The anatomy of a test class: `extends TestCase`, `test*` methods or `#[Test]` attribute
- `setUp()` and `tearDown()` — before/after each test method
- Core assertions: `assertEquals`, `assertSame`, `assertTrue`, `assertNull`, `assertCount`, `assertInstanceOf`
- Exception testing: `expectException()` and `expectExceptionMessage()`
- Running tests: `./vendor/bin/phpunit` — interpreting green, yellow, and red
- `phpunit.xml` — test suite configuration

### Key assertion patterns
```php
$this->assertSame($expected, $actual);         // strict equality + same type
$this->assertEquals($expected, $actual);       // loose equality
$this->assertCount(3, $collection);            // count of array/Countable
$this->assertInstanceOf(MyInterface::class, $obj);
$this->expectException(\InvalidArgumentException::class);
```

### Examples
```
examples/
  01-first-test.php               ← A test file that tests nothing (empty class) — just to run phpunit
  02-assertions.php               ← All core assertions demonstrated
  03-exception-testing.php        ← expectException + expectExceptionMessage
  04-setup-and-teardown.php       ← setUp / tearDown lifecycle
```

### Challenge
- Install PHPUnit in the course project
- Write a full test class for the `Money` value object from Lesson 2.1
- All tests must pass

---

## Lesson 5.2 — Unit Testing with Fakes and Stubs

> **The core skill: test one class at a time by replacing its dependencies.**

### Topics

- The unit test contract: one class under test, all deps are test doubles
- Writing anonymous class fakes inline (Module 2.4 pays off here)
- Stub pattern: returns a fixed value so you can test the class's reaction
- Spy pattern: records calls so you can assert what was invoked
- Null Object pattern in tests: silent fakes for dependencies you don't care about
- Testing the failure path — stubs that throw exceptions or return failure values
- What NOT to mock: value objects, DTOs, the class under test itself

### The four double types in code
```php
// Fake — lightweight working implementation
$fakeDb = new class implements DatabaseInterface {
    private array $data = [1 => ['id' => 1, 'name' => 'Alice']];
    public function query(string $sql, array $p = []): array {
        return $this->data[$p[0] ?? 0] ? [$this->data[$p[0]]] : [];
    }
    public function execute(string $sql, array $p = []): bool { return true; }
};

// Stub — returns fixed value regardless of input
$stubGateway = new class implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool { return true; }
};

// Spy — records what was called
$spyMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// Null Object — does nothing, satisfies the interface
$nullLogger = new class implements LoggerInterface {
    public function log(string $level, string $message): void {}
};
```

### Examples
```
examples/
  01-stub-pattern.php             ← Stub returns controlled value
  02-spy-pattern.php              ← Spy records calls for assertion
  03-testing-failure-paths.php    ← Stubs that throw or return failure
  04-null-object-in-tests.php     ← Null Objects for irrelevant deps
```

### Challenge
- Write a full unit test suite for `OrderService` (from Module 3)
- Use anonymous class doubles for all three dependencies
- Test: success path, payment failure path, product not found path

---

## Lesson 5.3 — Test-Driven Development (TDD)

> **Let the failing test drive the design. Red → Green → Refactor.**

### Topics

- The TDD cycle: **Red** (write a failing test), **Green** (write the minimum code to pass), **Refactor** (clean up without breaking tests)
- Why TDD produces better-designed classes — the test is the first caller, so it forces the API to be usable
- Outside-in TDD: start with the behaviour you want, let it pull the design inward
- When NOT to use TDD — exploratory code, throwaway scripts, framework config
- TDD with anonymous class doubles — write the test, define the interface you need, implement later
- The "test as specification" mindset — the test describes the system's contract

### The TDD cycle illustrated
```
1. RED:     Write test for behaviour that does not yet exist → test fails
2. GREEN:   Write the simplest code that makes the test pass
3. REFACTOR: Clean up the implementation — tests still pass
4. REPEAT:  Add the next test
```

### Example TDD session (full walkthrough)
```
Build a PasswordResetService from scratch using TDD:
  Test 1: generateToken() returns a 64-character string
  Test 2: storeToken() persists the token to the repository
  Test 3: isTokenValid() returns true for a token stored within 1 hour
  Test 4: isTokenValid() returns false for an expired token
  Test 5: invalidateToken() marks the token as used
```

### Examples
```
examples/
  01-red-green-refactor.php       ← One complete TDD cycle, annotated
  02-outside-in-tdd.php           ← Start from the behaviour, pull the design inward
  03-tdd-with-doubles.php         ← Using anonymous stubs during TDD
```

### Challenge
- Build `PasswordResetService` from scratch using TDD
- Write each test BEFORE writing the implementation method
- End with 5 passing tests and a fully implemented service

---

## Lesson 5.4 — Integration Testing with a Real Container

> **Test the wired system — not individual classes.**

### Topics

- The difference between unit tests (fakes) and integration tests (real implementations)
- Booting a PHP-DI container in a test's `setUp()` method
- Testing a full request cycle with a real database (SQLite in-memory)
- The test database: `PDO('sqlite::memory:')` — fast, isolated, no setup needed
- Cleaning state between tests: `setUp()` / `tearDown()` with database migrations
- What to test at the integration level vs unit level
- Testing HTTP routes with Slim's request simulation

### When to write integration tests
- When you need to verify that the wiring is correct (the container binds interfaces correctly)
- When you need to test database queries (real SQL, real schema)
- When you need to test framework routing (real HTTP cycle)
- NOT for testing individual class logic — that belongs in unit tests

### Examples
```
examples/
  01-container-in-tests.php       ← Boot PHP-DI container in setUp()
  02-sqlite-integration-test.php  ← Test with a real in-memory database
  03-slim-route-test.php          ← Test HTTP routes via Slim request simulation
```

### Challenge
- Write integration tests for the Slim PHP API from Lesson 4.5
- Use SQLite in-memory for the database
- Test all three routes: success, validation error, not found

---

## Lesson 5.5 — Testing Behaviours, Not Layouts

> **The anti-pattern lesson — avoid the tests that break on every refactor.**

### Topics

- What "testing the layout" means — and why it produces brittle tests
- The brittleness spectrum: from "tests the exact implementation" to "tests observable behaviour"
- Anti-pattern 1: asserting on how many constructor parameters a class has
- Anti-pattern 2: asserting on which private properties exist
- Anti-pattern 3: asserting that a specific method was called (mock overuse)
- When call assertions ARE appropriate: verifying side-effect invocations (was the email sent?)
- The "observable boundary" rule — only test what the class exposes to callers
- Refactoring with confidence: tests that test behaviour survive refactors; tests that test layout do not
- The test-to-implementation ratio: healthy vs over-specified suites

### The observable boundary rule
```
Test what the class RETURNS or DOES to its collaborators.
Do not test how it achieves that internally.

✅ Assert: processPayment() returns true when the gateway succeeds
✅ Assert: processPayment() calls the mailer exactly once on success
✅ Assert: processPayment() throws InvalidArgumentException for a negative amount

❌ Assert: $this->gateway is assigned in the constructor
❌ Assert: the logger->log() was called with the exact string "Processing payment"
           (unless that exact string is part of a contract)
❌ Assert: the class has a private $cache property
```

### Examples
```
examples/
  01-brittle-vs-resilient-tests.php   ← Same feature, two test styles
  02-refactor-without-breaking.php    ← Refactor implementation, tests still green
  03-when-to-assert-on-calls.php      ← Mock vs spy: when to care about invocations
```

### Challenge
- Given a test suite with 5 brittle tests, identify each anti-pattern
- Rewrite all 5 tests to test behaviour instead of layout
- Confirm all 5 still pass after a provided refactor of the implementation

---

## ✅ Module 5 Checklist

- [ ] Lesson 5.0 — Why Testing Requires DI
- [ ] Lesson 5.1 — PHPUnit Fundamentals + PHPUnit installed in project
- [ ] Lesson 5.2 — Unit Testing with Fakes and Stubs
- [ ] Lesson 5.3 — TDD: Red, Green, Refactor
- [ ] Lesson 5.4 — Integration Testing with a Real Container
- [ ] Lesson 5.5 — Testing Behaviours, Not Layouts

---

## 🛠️ Tools Required for This Module

```bash
# Install PHPUnit (run from project root)
composer require --dev phpunit/phpunit

# Run all tests
./vendor/bin/phpunit

# Run a specific test file
./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/tests/
```

**`phpunit.xml` (place in project root):**
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

---

## 📖 Reference

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPUnit Assertions](https://docs.phpunit.de/en/11.0/assertions.html)
- [Test Doubles (Martin Fowler)](https://martinfowler.com/bliki/TestDouble.html)
- [TDD by Example — Kent Beck](https://www.amazon.com/Test-Driven-Development-Kent-Beck/dp/0321146530)
- [Growing Object-Oriented Software Guided by Tests](https://www.growing-oo-software.com/)