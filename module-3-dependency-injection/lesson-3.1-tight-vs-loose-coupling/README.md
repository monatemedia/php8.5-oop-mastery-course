# Lesson 3.1 — Tight vs Loose Coupling
> **Module 3: Dependency Injection & IoC** · PHP 8.4 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-3.1-tight-vs-loose-coupling/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-what-coupling-is.php            ← Definition, measurement, vocabulary
│   ├── 02-the-new-keyword-smell.php       ← Why new inside a constructor is a problem
│   ├── 03-cost-of-tight-coupling.php      ← Untestable, inflexible, hard to swap
│   └── 04-identifying-coupling.php        ← Reading code and spotting every violation
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter.php
│   └── solution.php
│
└── quiz/
    └── QUIZ.md
```

---

## 1 — What Is Coupling?

**Coupling** is the degree to which one class depends on the internal details of another. The more a class knows about the specifics of what it depends on, the more tightly coupled it is.

Think of two gears. Tightly coupled gears are welded together — you cannot turn one without the other, and replacing either requires dismantling the whole machine. Loosely coupled gears mesh at the teeth only — you can replace one without affecting the other, as long as the teeth still fit.

In code, coupling shows up as:

- A class that creates its own dependencies with `new`
- A class that depends on a concrete class name rather than an interface
- A class that reaches into another class's internals (properties, private methods)
- A class that assumes specific behaviour of its dependencies without a contract

---

## 2 — Measuring Coupling

There is no single numeric metric, but these are the questions to ask:

### Q1 — Can I test this class without running its dependencies?
```php
class OrderService {
    public function __construct() {
        $this->db     = new MySQLDatabase();  // Real DB required to test
        $this->mailer = new SmtpMailer();     // Real SMTP required to test
    }
}
```
If the answer is NO, the class is tightly coupled to its dependencies.

### Q2 — Can I swap a dependency without editing this class?
```php
class ReportGenerator {
    private CsvExporter $exporter; // Hardcoded to CSV

    public function __construct() {
        $this->exporter = new CsvExporter();
    }
}
```
If swapping to `JsonExporter` requires editing `ReportGenerator`, it is tightly coupled.

### Q3 — How many concrete class names does this class know about?
Each `new ConcreteClass()` inside a class is a coupling point. Count them — more than zero is worth examining.

### Q4 — Does this class depend on the interface or the implementation?
```php
// Coupled to implementation:
private MySQLDatabase $db;

// Coupled to interface only:
private DatabaseInterface $db;
```

---

## 3 — Why `new ClassName()` Inside a Constructor Is a Design Smell

When a class calls `new` on its dependencies, it takes on three responsibilities it should not have:

**Responsibility 1: Creation** — the class decides which concrete class to use.
**Responsibility 2: Configuration** — the class knows what constructor arguments the dependency needs.
**Responsibility 3: Lifetime management** — the class controls when the dependency is created.

This violates the Single Responsibility Principle and makes the class impossible to test in isolation.

```php
// BEFORE — tightly coupled
class OrderProcessor {
    private PaymentGateway $gateway;
    private Logger         $logger;
    private Database       $db;

    public function __construct() {
        // ❌ Three concrete dependencies hardwired here
        $this->gateway = new StripeGateway('sk_live_abc123');
        $this->logger  = new FileLogger('/var/log/orders.log');
        $this->db      = new MySQLDatabase('localhost', 'orders_db', 'root', 'pass');
    }

    public function process(array $order): bool {
        $this->logger->log("Processing order #{$order['id']}");
        // ... business logic
        return true;
    }
}

// To test this:
$processor = new OrderProcessor();
// ↑ Immediately tries to connect to MySQL, the Stripe API, and open a log file.
// No real infrastructure = test fails before the first assertion.
```

The problem is not that these dependencies exist — it is that `OrderProcessor` is both **using** them and **building** them. That is two different jobs.

---

## 4 — The Three Costs of Tight Coupling

### Cost 1 — Untestability

A tightly coupled class cannot be tested without all its real dependencies running. For `OrderProcessor` above, you need:
- A live MySQL database with the right schema
- A Stripe account with a valid API key
- Write permission to `/var/log/orders.log`

In a test suite, any of these might be unavailable, slow, or have side effects (real charges). **Result: no unit tests, or fragile integration tests.**

### Cost 2 — Inflexibility

Every time the business changes — "switch from Stripe to PayFast", "log to a cloud service instead of a file" — you must edit `OrderProcessor` itself. Editing working code introduces regression risk.

```
Change request: Switch payment provider from Stripe to PayFast.
Files to edit with tight coupling: OrderProcessor.php, StripeGateway.php, and every other
class that creates StripeGateway directly.
Files to edit with loose coupling: Only the one line where StripeGateway is wired up.
```

### Cost 3 — Hard to Swap

Tight coupling makes it impossible to use the same class in different contexts:
- Test context: use a fake payment gateway that never charges
- Staging context: use a Stripe test mode gateway
- Production context: use the live Stripe gateway

With `new StripeGateway()` hardwired, all three contexts are forced to use the same concrete class.

---

## 5 — Types of Coupling (from worst to acceptable)

| Type | Description | Example |
|------|-------------|---------|
| **Content coupling** | Class A accesses private internals of Class B | `$b->privateProperty` |
| **Common coupling** | Classes share a global variable or static state | `Database::$instance` |
| **Control coupling** | Class A passes a flag that controls B's behaviour | `process(true)` (what does `true` mean?) |
| **Stamp coupling** | Class A passes more data than B needs | Passing the whole `$user` when only `$user->id` is needed |
| **Data coupling** | Classes share only what is needed via parameters | Passing `int $userId` — acceptable |
| **Message coupling** | Classes communicate only via interface methods | ✅ The goal |

---

## 6 — Identifying Coupling in Code — the Five Smells

**Smell 1: `new` inside a constructor or method**
```php
public function __construct() {
    $this->db = new MySQLDatabase(); // ❌ Coupling point
}
```

**Smell 2: Concrete class name as a property type**
```php
private MySQLDatabase $db;       // ❌ Coupled to MySQL
private DatabaseInterface $db;   // ✅ Coupled to abstraction
```

**Smell 3: Static method calls on concrete classes**
```php
$result = MySQLDatabase::getInstance()->query($sql); // ❌ Hidden coupling
```

**Smell 4: Global state / singleton access**
```php
$config = Config::getInstance(); // ❌ Cannot swap or test
```

**Smell 5: Hard-coded configuration strings**
```php
$this->db = new MySQLDatabase('localhost', 'mydb', 'root', 'pass'); // ❌
```

---

## 7 — Loose Coupling: The Goal

Loose coupling means a class knows only the **interface** of its dependencies, not the concrete implementation. The dependency is passed in from outside — the class never creates it.

```php
// AFTER — loosely coupled
interface PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool;
}

interface LoggerInterface {
    public function log(string $message): void;
}

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
}

class OrderProcessor {
    public function __construct(
        private PaymentGatewayInterface $gateway,  // ✅ Interface only
        private LoggerInterface         $logger,   // ✅ Interface only
        private DatabaseInterface       $db        // ✅ Interface only
    ) {}

    public function process(array $order): bool {
        $this->logger->log("Processing order #{$order['id']}");
        // ... business logic
        return true;
    }
}

// Test: inject fakes — no real infrastructure needed
$processor = new OrderProcessor(
    new FakeGateway(),    // Never charges
    new NullLogger(),     // Silently discards
    new InMemoryDb()      // In-memory array
);

// Production: inject real implementations
$processor = new OrderProcessor(
    new StripeGateway($apiKey),
    new FileLogger($logPath),
    new MySQLDatabase($config)
);
```

The class is identical in both cases. Only the wiring changes.

---

## 8 — Quick Reference: Coupling Smell Checklist

Run through this checklist on any class you write or review:

```
□ Does the constructor call `new` on any dependency?
□ Are any property types concrete class names (not interfaces)?
□ Does the class call static methods on concrete classes?
□ Does the class access global state or singletons?
□ Does the class have hard-coded configuration (DSNs, API keys, paths)?
□ Does the class pass more data to a collaborator than that collaborator needs?
□ Would adding a test require real infrastructure (database, API, filesystem)?

If you answered YES to any of these → coupling violation present.
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 3 (the `new` smell) and 4 (the three costs)
- [ ] Run and study `examples/01-what-coupling-is.php`
- [ ] Run and study `examples/02-the-new-keyword-smell.php`
- [ ] Run and study `examples/03-cost-of-tight-coupling.php`
- [ ] Run and study `examples/04-identifying-coupling.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **3.2 — Constructor Injection** — the preferred pattern for eliminating every coupling violation found in this lesson.*