# Lesson 5.2 — Unit Testing with Fakes and Stubs
> **Module 5: Automated Testing & TDD** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-5.2-unit-testing-with-fakes-and-stubs/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-stub-pattern.php                ← Stub returns controlled value
│   ├── 02-spy-pattern.php                 ← Spy records calls for assertion
│   ├── 03-testing-failure-paths.php       ← Stubs that throw or return failure
│   └── 04-null-object-in-tests.php        ← Null Objects for irrelevant deps
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── OrderService.php                   ← The class under test
│   ├── starter/
│   │   └── OrderServiceTest.php
│   └── solution/
│       └── OrderServiceTest.php
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — Sections 2 (the four double types) and 6 (what NOT to double) are the most important.
2. Run each example via PHPUnit and read the output.
3. Complete the challenge.
4. Take the quiz cold.

---

## 1 — The Unit Test Contract

A **unit test** tests exactly one class in isolation. Every dependency of that class is replaced with a test double — a controlled substitute that behaves exactly as the test needs.

```
Unit test = (Class under test) + (All dependencies replaced by doubles)
```

This is why Modules 1–4 mattered: constructor injection creates the seam that lets you swap real dependencies for doubles. A class that creates its own dependencies (`new MySQLDatabase()` inside a constructor) cannot be unit tested — there is no way to insert a fake.

```php
// ✅ Testable — dependencies injected, swappable
class OrderService {
    public function __construct(
        private ProductRepositoryInterface $products,
        private PaymentGatewayInterface    $gateway,
        private MailerInterface            $mailer,
        private LoggerInterface            $logger
    ) {}
}

// Test: inject four doubles — no real database, no real payment, no real email
$service = new OrderService($fakeProducts, $stubGateway, $spyMailer, $nullLogger);
```

---

## 2 — The Four Test Double Types

### Fake
A lightweight but **working** implementation. Has real internal logic (e.g. an in-memory store), but avoids expensive infrastructure.

```php
$fakeProducts = new class implements ProductRepositoryInterface {
    private array $store = [
        1 => ['id' => 1, 'name' => 'Widget Pro', 'price' => 29999, 'sku' => 'WDG-001'],
    ];

    public function findById(int $id): ?array {
        return $this->store[$id] ?? null;
    }

    public function findAll(): array {
        return array_values($this->store);
    }
};
```

**When to use:** The class under test needs the dependency to actually do something — look things up, store things, compute values. You want real behaviour without real infrastructure.

---

### Stub
Returns a **fixed, predetermined value** regardless of input. The simplest possible double.

```php
// Always succeeds — controls the class under test's happy path
$stubGateway = new class implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        return true;
    }
};

// Always fails — controls the failure path
$failingGateway = new class implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        return false;
    }
};
```

**When to use:** You need to control what the dependency *returns* so you can test how the class under test *reacts* to that return value. The stub itself is not what you are testing.

---

### Spy
Records calls made to it. You inspect the records after the fact.

```php
$spyMailer = new class implements MailerInterface {
    public array $sent = [];   // public so the test can read it

    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// After running the class under test:
$this->assertCount(1, $spyMailer->sent);
$this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
```

**When to use:** You need to verify that the class under test *called* the dependency correctly — what arguments were passed, how many times it was called. Use for side effects: emails sent, events dispatched, logs written.

---

### Null Object
Implements the interface but does nothing. The "off switch" for a dependency you do not care about in a particular test.

```php
$nullLogger = new class implements LoggerInterface {
    public function log(string $level, string $message): void {
        // Intentionally silent — this test does not care about logging
    }
};
```

**When to use:** A dependency is required by the constructor but irrelevant to the behaviour this test is verifying. Using a Null Object keeps the test focused on what matters.

---

## 3 — Anonymous Classes Are the Ideal PHP Test Double

PHP's anonymous class syntax (Module 2.4) is the cleanest way to write test doubles:

```php
// All four types, inline, no separate files needed
$fakeDb      = new class implements DatabaseInterface { /* ... */ };
$stubGateway = new class implements PaymentGatewayInterface { /* ... */ };
$spyMailer   = new class implements MailerInterface { public array $sent = []; /* ... */ };
$nullLogger  = new class implements LoggerInterface { public function log(string $l, string $m): void {} };
```

Advantages over mocking frameworks:
- **Type-safe** — must implement the full interface contract (PHP enforces it)
- **Readable** — the double is defined right where it is used
- **No magic** — what the double does is explicit and obvious
- **No framework dependency** — works with PHPUnit or any test runner

---

## 4 — Placing Doubles in setUp()

When the same double is used across multiple tests, define it in `setUp()`:

```php
class OrderServiceTest extends TestCase
{
    private OrderService  $service;
    private SpyMailer     $spyMailer;   // inner class defined below
    private FakeProducts  $fakeProducts;

    protected function setUp(): void
    {
        $this->fakeProducts = new class implements ProductRepositoryInterface {
            private array $store = [
                1 => ['id' => 1, 'name' => 'Widget Pro', 'price' => 29999, 'sku' => 'WDG-001'],
            ];
            public function findById(int $id): ?array { return $this->store[$id] ?? null; }
            public function findAll(): array { return array_values($this->store); }
        };

        $this->spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $subject, string $body): bool {
                $this->sent[] = compact('to', 'subject', 'body');
                return true;
            }
        };

        $this->service = new OrderService(
            $this->fakeProducts,
            new class implements PaymentGatewayInterface {
                public function charge(float $amount, string $token): bool { return true; }
            },
            $this->spyMailer,
            new class implements LoggerInterface {
                public function log(string $level, string $message): void {}
            }
        );
    }
}
```

For tests that need a **different** stub (e.g. the payment fails), create the double inline inside that specific test:

```php
public function testPlaceOrderFailsWhenPaymentDeclined(): void
{
    // Override the gateway with a failing stub for this test only
    $failingGateway = new class implements PaymentGatewayInterface {
        public function charge(float $amount, string $token): bool { return false; }
    };

    $service = new OrderService(
        $this->fakeProducts,
        $failingGateway,          // ← inline override
        $this->spyMailer,
        new class implements LoggerInterface { public function log(string $l, string $m): void {} }
    );

    $result = $service->placeOrder(productId: 1, email: 'alice@example.com');

    $this->assertFalse($result['success']);
    $this->assertCount(0, $this->spyMailer->sent); // no email on failure
}
```

---

## 5 — Testing Failure Paths

The failure path is as important as the success path. Stubs that return failure values or throw exceptions let you test exactly how the class under test handles problems:

```php
// Stub that throws — simulates a network failure
$throwingGateway = new class implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        throw new \RuntimeException('Payment gateway timeout');
    }
};

// Stub that returns null — simulates a missing record
$emptyRepo = new class implements ProductRepositoryInterface {
    public function findById(int $id): ?array { return null; }
    public function findAll(): array { return []; }
};
```

Always ask: "What happens when X fails?" and write a test for it.

---

## 6 — What NOT to Double

Not everything should be replaced with a test double:

| Do NOT double | Why | What to do instead |
|---------------|-----|--------------------|
| The class under test itself | You are testing it — use the real thing | Instantiate it normally |
| Value objects (`Money`, `OrderId`) | No infrastructure, pure behaviour | Use the real class |
| DTOs / plain arrays | No behaviour, just data | Use the real thing |
| PHP built-in classes (`DateTime`) | Lightweight, no I/O | Use the real class |
| Exceptions | Just `throw new \InvalidArgumentException(...)` | Use the real exception |

The test double exists to **isolate infrastructure and side effects** — databases, networks, email, time. Pure in-memory computation should use the real implementation.

---

## 7 — The Spy vs Mock Distinction

A **spy** records calls after the fact. You assert on the spy after running the class under test:

```php
// Run the code
$service->placeOrder(1, 'alice@example.com');

// Assert on the spy
$this->assertCount(1, $this->spyMailer->sent);
$this->assertSame('alice@example.com', $this->spyMailer->sent[0]['to']);
```

A **mock** asserts call expectations automatically, typically via a mocking framework like PHPUnit's `createMock()`. Mocks fail the test if the expected call is never made.

For most cases, **spies are preferable** to mocks:
- Spies are explicit — you see the assertion in the test
- Spies do not fail if you add a call the mock was not set up for
- Spies do not require a mocking framework
- Spies are easier to debug when tests fail

Use mocks sparingly, and only when the *absence* of a call must also be detected automatically.

---

## 8 — Quick Reference

```php
// ── The four double types ─────────────────────────────────────────────────

// Fake: lightweight working implementation
$fakeDb = new class implements DatabaseInterface {
    private array $store = [1 => ['id' => 1, 'name' => 'Widget']];
    public function query(string $sql, array $p = []): array {
        return isset($this->store[$p[0] ?? 0]) ? [$this->store[$p[0]]] : [];
    }
    public function execute(string $sql, array $p = []): bool { return true; }
};

// Stub: returns a fixed value
$stubGateway = new class implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool { return true; }
};

// Spy: records calls
$spyMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// Null Object: does nothing
$nullLogger = new class implements LoggerInterface {
    public function log(string $level, string $message): void {}
};

// ── Assertions on spies ──────────────────────────────────────────────────
$this->assertCount(1, $spyMailer->sent);
$this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
$this->assertStringContainsString('Order Confirmed', $spyMailer->sent[0]['subject']);

// ── Stub that throws ─────────────────────────────────────────────────────
$throwingGateway = new class implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        throw new \RuntimeException('Network error');
    }
};
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 2 (the four types), 5 (failure paths), and 6 (what NOT to double)
- [ ] Run and study `examples/01-stub-pattern.php`
- [ ] Run and study `examples/02-spy-pattern.php`
- [ ] Run and study `examples/03-testing-failure-paths.php`
- [ ] Run and study `examples/04-null-object-in-tests.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter/OrderServiceTest.php`
- [ ] Check your work against `challenge/solution/OrderServiceTest.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **5.3 — Test-Driven Development** — let failing tests drive your class design. Red → Green → Refactor.*