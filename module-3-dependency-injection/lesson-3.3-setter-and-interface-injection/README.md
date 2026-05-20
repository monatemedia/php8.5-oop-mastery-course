# Lesson 3.3 — Setter & Interface Injection
> **Module 3: Dependency Injection & IoC** · PHP 8.4 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-3.3-setter-and-interface-injection/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-setter-injection.php            ← Setter injection: syntax and use cases
│   ├── 02-null-object-default.php         ← The NullObject pattern as a safe default
│   ├── 03-interface-injection.php         ← The dependency provides the setter contract
│   └── 04-when-to-use-which.php           ← Decision guide: constructor vs setter vs interface
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

## 1 — Three Forms of Dependency Injection

So far in this module you have learned constructor injection — the preferred pattern. There are two other forms:

| Form | How dependency is provided | Use case |
|------|---------------------------|----------|
| **Constructor injection** | Constructor parameter | **Required** dependency — class cannot function without it |
| **Setter injection** | `setDependency()` method | **Optional** dependency — class has a sensible default, but the caller can override it |
| **Interface injection** | Interface declares a `setX()` method; the class must implement it | Framework calls the setter — the class announces what it needs |

The key question that determines which to use:

> **Is this dependency required for the class to function at all?**
> - YES → Constructor injection
> - NO, it is optional → Setter injection

---

## 2 — Setter Injection

Setter injection means providing a dependency via a `set*()` method after the object has been constructed.

```php
class ReportService {
    private ?LoggerInterface $logger = null; // Optional — null by default

    // Required dependencies via constructor
    public function __construct(
        private DatabaseInterface $db
    ) {}

    // Optional dependency via setter
    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function generate(int $id): array {
        // Safe to call even when no logger was set
        $this->logger?->log('INFO', "Generating report #{$id}");
        return $this->db->query('SELECT * FROM reports WHERE id = ?', [$id]);
    }
}
```

Usage:

```php
// Without logger (the class still works)
$service = new ReportService($db);
$service->generate(1);

// With logger (caller opts in)
$service = new ReportService($db);
$service->setLogger(new FileLogger('/var/log/reports.log'));
$service->generate(1);
```

---

## 3 — The Null Object Default

A common improvement over `?LoggerInterface $logger = null` is to default to a **Null Object** — an implementation that does nothing. This eliminates null checks entirely:

```php
class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        // Intentionally silent
    }
}

class ReportService {
    private LoggerInterface $logger;

    public function __construct(private DatabaseInterface $db) {
        $this->logger = new NullLogger(); // Safe default — no null checks needed
    }

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function generate(int $id): array {
        $this->logger->log('INFO', "Generating #{$id}"); // Always safe — no null check
        return $this->db->query('SELECT * FROM reports WHERE id = ?', [$id]);
    }
}
```

With the Null Object pattern:
- The class is always in a valid state (no null dereferencing possible)
- The `?->` nullsafe operator is unnecessary
- Adding a real logger is a one-call opt-in

---

## 4 — Fluent Setter Interface (Method Chaining)

Setters can return `static` to enable a fluent builder interface:

```php
class Mailer {
    private LoggerInterface $logger;
    private ?string         $fromAddress = null;
    private int             $maxRetries  = 3;

    public function __construct(private TransportInterface $transport) {
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): static {
        $this->logger = $logger;
        return $this;
    }

    public function setFrom(string $address): static {
        $this->fromAddress = $address;
        return $this;
    }

    public function setMaxRetries(int $retries): static {
        $this->maxRetries = $retries;
        return $this;
    }
}

// Fluent chaining at the composition root
$mailer = (new Mailer($smtpTransport))
    ->setLogger($fileLogger)
    ->setFrom('noreply@example.com')
    ->setMaxRetries(5);
```

---

## 5 — Interface Injection

Interface injection is less common than the other two forms. The idea is that an **interface declares the setter method**. Any class that wants to receive that dependency must implement the interface — announcing to the framework that it needs to be called.

```php
// The "injection point" interface
interface LoggerAware {
    public function setLogger(LoggerInterface $logger): void;
}

// Any class that needs a logger implements LoggerAware
class OrderService implements LoggerAware {
    private LoggerInterface $logger;

    public function __construct(
        private DatabaseInterface $db,
        private PaymentGatewayInterface $gateway
    ) {
        $this->logger = new NullLogger();
    }

    // Framework calls this because OrderService implements LoggerAware
    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function placeOrder(array $order): bool {
        $this->logger->log('INFO', "Placing order #{$order['id']}");
        // ...
        return true;
    }
}
```

A container or framework that knows about `LoggerAware` can automatically call `setLogger()` on any class that implements it — without you having to add `$logger` to every constructor.

**Where you see interface injection in the wild:**
- PSR-3 `LoggerAwareInterface` (the standard PHP logger injection interface)
- Symfony's `LoggerAwareTrait`
- Laravel's `Loggable` concern

---

## 6 — PSR-3: A Real-World Interface Injection Standard

PSR-3 (PHP-FIG Logger Interface) defines exactly this pattern:

```php
// PSR-3 interface injection contract
interface LoggerAwareInterface {
    public function setLogger(LoggerInterface $logger): void;
}

// PSR-3 provides a default trait implementation
trait LoggerAwareTrait {
    protected LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }
}

// Your class opts in — zero constructor change
class PaymentProcessor implements LoggerAwareInterface {
    use LoggerAwareTrait; // Free implementation of setLogger()

    public function __construct(
        private PaymentGatewayInterface $gateway
    ) {}

    public function charge(float $amount, string $token): bool {
        $this->logger->log('INFO', "Charging R{$amount}");
        return $this->gateway->charge($amount, $token);
    }
}
```

A PSR-3 compliant container or framework sees `implements LoggerAwareInterface` and automatically calls `setLogger($logger)` after construction.

---

## 7 — When to Use Which Pattern

```
Is the dependency REQUIRED for the class to function?
  YES → Constructor injection (class cannot be constructed without it)
  NO  ↓

Is this a standard "awareness" contract (LoggerAware, EventAware)?
  YES → Interface injection (implement the interface, use the trait)
  NO  ↓

Is this a caller-controlled option where the class has a sensible default?
  YES → Setter injection (provide a NullObject default, allow override)
  NO  → You probably do not need DI here — pass it as a method argument instead
```

### Practical guidelines

| Scenario | Pattern |
|----------|---------|
| Database connection — class useless without it | Constructor |
| Logger — class works fine without logging | Setter (with NullLogger default) |
| Payment gateway — required for payment | Constructor |
| Cache — nice to have, class works without it | Setter (with NullCache default) |
| Mailer — required for order confirmation | Constructor |
| Event dispatcher — optional side effect | Setter (with NullDispatcher default) |
| Framework-provided logger (PSR-3) | Interface injection |

---

## 8 — Common Mistakes to Avoid

| Mistake | Why it is wrong | Fix |
|---------|----------------|-----|
| Using setter injection for required deps | The class can be used before the dep is set — null reference error | Use constructor injection |
| No NullObject default | Method calls on null crash — requires null checks everywhere | Always provide a NullObject default for optional setters |
| Forgetting to return `static` from setters | Breaks fluent chaining | Return `static` from all setters that support chaining |
| Using interface injection for all deps | Overcomplicates the class contract | Reserve interface injection for framework/PSR patterns |
| Setter injection for security-critical deps | Auth, encryption keys — must be required | Constructor injection — these cannot be optional |

---

## 9 — Quick Reference

```php
// Setter injection with NullObject default
class MyService {
    private LoggerInterface $logger;

    public function __construct(private RequiredDep $dep) {
        $this->logger = new NullLogger(); // Safe default
    }

    public function setLogger(LoggerInterface $logger): static {
        $this->logger = $logger;
        return $this;
    }
}

// Interface injection — class announces what it needs
interface LoggerAware {
    public function setLogger(LoggerInterface $logger): void;
}

class MyOtherService implements LoggerAware {
    private LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }
}

// Composition root
$service = new MyService($dep);                       // Required dep in constructor
$service->setLogger($logger);                         // Optional dep via setter

$other = new MyOtherService();                        // Framework calls setLogger()
// Framework: if ($other instanceof LoggerAware) $other->setLogger($logger);
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially the decision flowchart in Section 7
- [ ] Run and study `examples/01-setter-injection.php`
- [ ] Run and study `examples/02-null-object-default.php`
- [ ] Run and study `examples/03-interface-injection.php`
- [ ] Run and study `examples/04-when-to-use-which.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **3.4 — Inversion of Control (IoC)** — the principle that ties constructor and setter injection together.*