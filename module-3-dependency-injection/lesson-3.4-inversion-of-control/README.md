# Lesson 3.4 — Inversion of Control (IoC)
> **Module 3: Dependency Injection & IoC** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-3.4-inversion-of-control/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-hollywood-principle.php         ← "Don't call us, we'll call you"
│   ├── 02-dip-in-practice.php             ← High-level modules, abstractions, details
│   ├── 03-dip-vs-di.php                   ← Principle vs technique
│   └── 04-manual-ioc-container.php        ← Building an IoC wiring function from scratch
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

## 1 — What Is Inversion of Control?

**Inversion of Control (IoC)** is an architectural principle. It says:

> The flow of control in a program should be inverted — instead of application code calling a framework or library to get things done, the framework calls the application code at the right time.

More practically for our purposes: instead of a class creating or finding its own dependencies (calling outward to get what it needs), dependencies are provided to it from the outside (the outside pushes them in).

This is "inversion" because normal procedural thinking has classes reaching out for what they need. IoC flips this: classes declare what they need, and something else (a container, a bootstrap file, a framework) provides it.

---

## 2 — The Hollywood Principle

The Hollywood Principle captures IoC in one memorable rule:

> **"Don't call us — we'll call you."**

In the context of OOP, this means:

- **Without IoC (you call the framework):**
```php
class OrderService {
    public function __construct() {
        $this->db = Database::getInstance(); // ← you reach out to get a DB
        $this->logger = new FileLogger();    // ← you reach out to build a logger
    }
}
```

- **With IoC (the framework calls you):**
```php
class OrderService {
    public function __construct(
        private DatabaseInterface $db,    // ← declared as a need
        private LoggerInterface   $logger // ← declared as a need
    ) {}
    // Something ELSE provides these — you don't go get them
}
```

The class declares what it needs. The container (or composition root) provides it. Control flows inward, not outward.

---

## 3 — The Dependency Inversion Principle (DIP)

DIP is the **D** in SOLID. It states two rules:

> **Rule 1:** High-level modules should not depend on low-level modules. Both should depend on abstractions.
>
> **Rule 2:** Abstractions should not depend on details. Details (concrete implementations) should depend on abstractions.

### Without DIP

```
OrderService (high-level)
    └─ depends on ──► MySQLDatabase (low-level, concrete)
    └─ depends on ──► SmtpMailer   (low-level, concrete)
    └─ depends on ──► StripeGateway (low-level, concrete)
```

`OrderService` is directly coupled to three concrete classes. Adding PostgreSQL or switching to Mailgun requires editing `OrderService`.

### With DIP

```
OrderService (high-level)
    └─ depends on ──► DatabaseInterface    (abstraction)
    └─ depends on ──► MailerInterface      (abstraction)
    └─ depends on ──► PaymentGatewayInterface (abstraction)

MySQLDatabase    implements DatabaseInterface
SmtpMailer       implements MailerInterface
StripeGateway    implements PaymentGatewayInterface
```

`OrderService` now depends on **abstractions only**. The concrete classes depend on the abstractions (they implement the interfaces). The dependency arrow from high-level to low-level has been **inverted** — both now point at the interface in the middle.

---

## 4 — DIP vs DI: The Principle vs the Technique

This distinction trips people up constantly. They are not the same thing.

| | DIP | DI |
|--|-----|----|
| **What it is** | A design principle | A technique |
| **What it says** | "Depend on abstractions, not concretions" | "Receive dependencies from outside rather than creating them" |
| **Scale** | Architectural — governs how layers relate | Implementation — governs how a single class receives a dependency |
| **Does it require injection?** | No — could be achieved with a service locator (badly) | No — injection does not require DIP (you could inject concrete classes) |
| **Are they related?** | Yes — DI is the preferred technique for implementing DIP | Yes — DIP defines what to inject (interfaces), DI defines how |

In practice: **DIP tells you to type-hint interfaces; DI tells you to receive them via constructor.** You need both to write clean, testable, container-ready code.

---

## 5 — Manual IoC: Building a Wiring Function

Before any container library existed, developers wrote IoC by hand — a single function or bootstrap file that wired the entire application graph. This is still the right thing to do in small projects.

```php
// bootstrap.php — the IoC wiring function
function buildApplication(array $config): OrderController {
    // Layer 1: infrastructure (concrete — the only `new` calls in the codebase)
    $db      = new MySQLDatabase($config['db_dsn']);
    $mailer  = new SmtpMailer($config['smtp_host'], $config['smtp_port']);
    $gateway = new StripeGateway($config['stripe_key']);
    $logger  = new FileLogger($config['log_path']);

    // Layer 2: repositories (depend on infrastructure)
    $orderRepo   = new OrderRepository($db, $logger);
    $productRepo = new ProductRepository($db, $logger);

    // Layer 3: services (depend on repositories + infrastructure)
    $orderService = new OrderService($orderRepo, $productRepo, $gateway, $mailer, $logger);

    // Layer 4: HTTP layer (depends on services)
    return new OrderController($orderService, $logger);
}

// index.php — call the wiring function once
$config     = require __DIR__ . '/config.php';
$controller = buildApplication($config);
$controller->dispatch($_REQUEST);
```

This is IoC. The application code (`OrderService`, `OrderController`) never calls `new` on anything. The wiring function at the top does all the construction and passes everything downward.

---

## 6 — The Pain of Manual IoC — Motivation for Containers

Manual IoC works — but it scales badly.

```
10 services  → manageable (one screen)
50 services  → tedious (scroll and trace)
200 services → error-prone (did I forget to pass $logger?)
1000 services → unmaintainable (full-time job keeping the wiring file updated)
```

The problems at scale:
1. **Boilerplate explosion** — every new class requires a new `$x = new X(...)` line
2. **Fragile ordering** — infrastructure must be created before repositories, which must be created before services
3. **Duplication** — `$logger` is passed to 40 different constructors
4. **Singleton management** — is `$db` shared or fresh per service? You must track this manually

**This is exactly the problem Module 4 solves.** PHP-DI reads your constructor type hints with Reflection and resolves the entire graph automatically. The container IS the IoC wiring function — automated.

---

## 7 — IoC, DI, and DIP Together

These three concepts work as a stack:

```
DIP (principle) ────────────► "Depend on abstractions — use interfaces as types"
    ↓ implemented via
DI  (technique) ────────────► "Receive dependencies via constructor / setter"
    ↓ scaled up by
IoC (pattern) ──────────────► "Something else wires the whole graph"
    ↓ automated by
Container (Module 4) ────────► "PHP-DI reads Reflection and wires automatically"
```

You cannot skip a layer. A container without DIP still wires concrete classes — tight coupling at scale. DI without IoC means every class still knows where its deps come from. IoC without DIP means the wiring function hardwires concrete dependencies.

---

## 8 — Quick Reference

```
Hollywood Principle: "Don't call us — we'll call you."
                     Classes declare needs; something else provides them.

DIP Rule 1:          High-level modules depend on abstractions, not low-level modules.
DIP Rule 2:          Abstractions don't depend on details; details depend on abstractions.

DIP vs DI:           DIP = what to type-hint (interfaces).
                     DI  = how to receive deps (constructor / setter).

Manual IoC:          One wiring function at the entry point.
                     All `new` calls for services live there and only there.

Container (preview): Automates the wiring function using Reflection.
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Section 4 (DIP vs DI) and Section 6 (motivation for containers)
- [ ] Run and study `examples/01-hollywood-principle.php`
- [ ] Run and study `examples/02-dip-in-practice.php`
- [ ] Run and study `examples/03-dip-vs-di.php`
- [ ] Run and study `examples/04-manual-ioc-container.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Module 3 complete. Next: **Module 4 — Container Automation with PHP-DI** — automate the wiring you just learned to do manually.*