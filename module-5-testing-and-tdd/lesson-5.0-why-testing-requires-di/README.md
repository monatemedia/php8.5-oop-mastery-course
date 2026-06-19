# Lesson 5.0 — Why Testing Requires DI
> **Module 5: Automated Testing & TDD** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-5.0-why-testing-requires-di/
├── README.md                                    ← Theory (you are here)
│
└── examples/
    ├── 01-why-tight-coupling-breaks-tests.php   ← Class that creates its own deps: untestable
    ├── 02-di-makes-testing-possible.php         ← Same class, injected: fully testable
    └── 03-the-four-double-types.php             ← Fake, Stub, Spy, Mock side by side
```

> **No challenge or quiz in this lesson** — it is a conceptual bridge. The challenge and quiz work begins in Lesson 5.1.

---

## 1 — The Payoff

You have spent four modules building clean, decoupled, injected code. This lesson explains *why* that investment pays off in testing:

> **A class that creates its own dependencies cannot be unit-tested.**

That is not a stylistic preference. It is a structural fact. If `OrderService` calls `new MySQLDatabase()` in its constructor, you cannot test `OrderService` without a running MySQL instance. The database is baked in. There is no seam to insert a fake.

Constructor injection creates that seam. When `OrderService` receives a `DatabaseInterface` via its constructor, you can pass anything that satisfies the interface — including a fake that returns controlled data without touching a database.

**Modules 1–4 built the seams. Module 5 uses them.**

---

## 2 — The Four Test Double Types

A **test double** is any object used in place of a real dependency in a test. There are four distinct types:

### Fake
A lightweight implementation that actually works, but uses simplified mechanics (in-memory instead of disk, for example).

```php
$fakeDb = new class implements DatabaseInterface {
    private array $store = [];
    public function query(string $sql, array $params = []): array {
        return array_values($this->store);
    }
    public function execute(string $sql, array $params = []): bool {
        $this->store[] = $params;
        return true;
    }
};
```

**When to use:** You need the dependency to actually do something meaningful, but you do not want real infrastructure.

### Stub
Returns a fixed, predetermined value regardless of input. Does not verify calls.

```php
$stubGateway = new class implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        return true; // always succeeds — controlled return value
    }
};
```

**When to use:** You need to control what the dependency *returns* so you can test how the class under test *reacts*.

### Spy
Records calls made to it. You assert on what was called after the fact.

```php
$spyMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// After running the class under test:
assert(count($spyMailer->sent) === 1);
assert($spyMailer->sent[0]['to'] === 'alice@example.com');
```

**When to use:** You need to verify that the class under test *called* the dependency correctly (side effects, not return values).

### Mock
A spy with built-in expectations that assert automatically. PHPUnit's `$this->createMock()` builds these. Manual mocks with anonymous classes are usually cleaner.

```php
// PHPUnit mock that expects exactly one send() call:
$mockMailer = $this->createMock(MailerInterface::class);
$mockMailer->expects($this->once())
           ->method('send')
           ->with($this->equalTo('alice@example.com'));
```

**When to use:** Sparingly. When the call itself IS the behaviour under test (e.g. verifying that an email was sent), a spy is usually clearer. Mocks tend to produce brittle tests (Rule 2).

---

## 3 — Anonymous Classes Are the Ideal PHP Test Double

PHP 8.0+ anonymous classes are the cleanest way to write test doubles in PHP. They are:
- Inline — no separate file to maintain
- Type-safe — must implement the interface contract
- Flexible — can have additional `public array $calls` properties for spying
- Readable — the double is defined right where it is used

```php
// All four types inline — no mocking framework needed for most cases
$fakeDb    = new class implements DatabaseInterface { /* ... */ };
$stubGw    = new class implements PaymentGatewayInterface { /* ... */ };
$spyMailer = new class implements MailerInterface { public array $sent = []; /* ... */ };
$nullLog   = new class implements LoggerInterface { public function log(string $l, string $m): void {} };
```

---

## 4 — The Test Environment as a Composition Root

Testing is not different from production — it is just a different composition root.

**Production composition root (`public/index.php`):**
```php
$container->bind(DatabaseInterface::class, MySQLDatabase::class);
$container->bind(MailerInterface::class,   SmtpMailer::class);
$service = $container->get(OrderService::class);
```

**Test composition root (inside a test method):**
```php
$fakeDb    = new class implements DatabaseInterface { /* ... */ };
$spyMailer = new class implements MailerInterface { /* ... */ };
$service   = new OrderService($fakeDb, $spyMailer, new NullLogger());
```

Both are wiring the same `OrderService`. The difference is what gets wired in.
**Testing is just IoC with fakes at the entry point.**

---

## 5 — Why This Module Connects to Modules 1–4

| What you built | Why it enables testing |
|----------------|----------------------|
| Interfaces (Lesson 1.1) | Test doubles implement the same interface as the real class |
| Composition over Inheritance (Lesson 1.4) | Composed services have visible constructor seams |
| Constructor Injection (Lesson 3.2) | The seam that lets you inject fakes instead of real deps |
| DIP — abstract types (Lesson 3.4) | Fakes satisfy the same interface contract as production code |
| PHP-DI container (Module 4) | Integration tests boot a real container with test definitions |

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 2 (the four double types) and 4 (test as composition root)
- [ ] Run and study `examples/01-why-tight-coupling-breaks-tests.php`
- [ ] Run and study `examples/02-di-makes-testing-possible.php`
- [ ] Run and study `examples/03-the-four-double-types.php`

---

*Next lesson: **5.1 — PHPUnit Fundamentals** — install PHPUnit and learn the anatomy of a proper test class.*