# Lesson 1.4 — Composition over Inheritance
> **Module 1: OOP Building Blocks** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-1.4-composition-over-inheritance/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-inheritance-vs-composition.php  ← The same problem solved both ways
│   ├── 02-deep-inheritance-trap.php       ← Why deep trees break under change
│   ├── 03-composing-behaviour.php         ← Building flexible classes with injected collaborators
│   ├── 04-recognising-the-smell.php       ← When extends is and is not appropriate
│   └── 05-bridge-to-di.php               ← How composition enables Dependency Injection
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

## Why This Lesson Exists

You now know interfaces, abstract classes, and traits — the three tools PHP provides for sharing behaviour. The question this lesson answers is: **when should you connect classes via `extends`, and when should you connect them by holding a reference to a collaborator?**

This is not a trivial question. Getting it wrong — inheriting when you should compose — is the single most common reason that OOP codebases become rigid, hard to test, and painful to extend. Getting it right is the prerequisite for everything in Modules 3 and 4.

---

## 1 — The Core Idea

**Inheritance** says: *"I am a kind of this thing."*
**Composition** says: *"I use this thing."*

```php
// Inheritance — NotificationService IS a DatabaseService
class NotificationService extends DatabaseService {
    public function notify(string $message): void {
        $this->query(...); // inherited — tightly coupled
    }
}

// Composition — NotificationService HAS a database
class NotificationService {
    public function __construct(
        private DatabaseInterface $db  // injected — loosely coupled
    ) {}

    public function notify(string $message): void {
        $this->db->query(...); // delegated
    }
}
```

The `extends` version works — until you need to use `NotificationService` with a different database driver, or in a context where `DatabaseService`'s constructor requires credentials you do not have. The composed version accepts any `DatabaseInterface` implementation, including a fake for tests.

---

## 2 — Why Inheritance Gets Overused

PHP developers reach for `extends` for three wrong reasons:

**Reason 1: Code reuse.** "I want `save()` from `BaseModel` in my `UserModel`."
Fix: Extract to a trait, or inject a repository collaborator.

**Reason 2: Getting a default.** "I want to call `parent::__construct()` to set up shared state."
Fix: Create the shared object explicitly and pass it in (constructor injection).

**Reason 3: Type grouping.** "I want `UserModel` and `PostModel` to be the same type."
Fix: Create an interface (`ModelInterface`) that both implement without inheriting from anything.

---

## 3 — The Deep Inheritance Trap

Inheritance chains longer than two levels almost always signal a design problem:

```
AbstractEntity
    └── BaseModel
            └── AuditableModel
                    └── UserModel
                            └── AdminUserModel
```

Problems this creates:

**Problem 1 — The fragile base class problem.** Change `AbstractEntity` and every subclass is affected — including `AdminUserModel` five levels down, which you may not have touched in months.

**Problem 2 — LSP violations become inevitable.** Every level must honour the contracts of every level above it. As the chain grows, this becomes increasingly hard to reason about (Lesson 2.0 covers this in depth).

**Problem 3 — Constructor coupling chains.** If `AbstractEntity.__construct()` requires a database connection, then `AdminUserModel` must pass one — even if it has no use for it.

**Problem 4 — Testing in isolation is impossible.** To unit test `AdminUserModel`, you must satisfy all four parent constructors. Each requires real infrastructure.

---

## 4 — The Practical Test: "Can I Replace `extends` With a Field?"

Apply this test to every `class X extends Y` you write:

```php
// Can I write this instead?
  private Y $y;
  public function __construct(Y $y) { $this->y = $y; }
  // and call $this->y->method() everywhere I used parent::method()
```

If the answer is YES → you should probably compose, not inherit.
If the answer is NO (e.g. you are adding new cases to an abstract method, or overriding template method steps) → inheritance may be correct.

---

## 5 — Composition in PHP: Four Patterns

### Pattern 1 — Constructor injection (the most common)

```php
class ReportService {
    public function __construct(
        private FormatterInterface $formatter,
        private StorageInterface   $storage
    ) {}

    public function generate(array $data): void {
        $formatted = $this->formatter->format($data);
        $this->storage->save($formatted);
    }
}
```

`ReportService` has no parent class. It composes two collaborators. Any formatter, any storage — swap them at the composition root.

### Pattern 2 — Setter injection (for optional behaviour)

```php
class DataProcessor {
    private LoggerInterface $logger;

    public function __construct(private DatabaseInterface $db) {
        $this->logger = new NullLogger(); // safe default
    }

    public function setLogger(LoggerInterface $logger): static {
        $this->logger = $logger;
        return $this;
    }
}
```

### Pattern 3 — Method parameter (per-call collaborator)

```php
class PriceCalculator {
    // No persistent collaborator — the discount strategy is passed per-call
    public function calculate(Money $price, DiscountStrategy $discount): Money {
        return $discount->apply($price);
    }
}
```

### Pattern 4 — Delegating decorator (wraps a collaborator to add behaviour)

```php
class LoggingGateway implements PaymentGatewayInterface {
    public function __construct(
        private PaymentGatewayInterface $inner,
        private LoggerInterface         $logger
    ) {}

    public function charge(float $amount, string $token): bool {
        $this->logger->log('INFO', "Charging R{$amount}");
        $result = $this->inner->charge($amount, $token);
        $this->logger->log('INFO', "Result: " . ($result ? 'ok' : 'fail'));
        return $result;
    }
}
```

`LoggingGateway` adds logging to *any* gateway without modifying it — Open/Closed Principle in action.

---

## 6 — When `extends` IS Correct

Inheritance is not wrong — it is just often misapplied. It is correct when:

1. **A genuine "is-a" relationship exists** — `AdminUser` genuinely is a subtype of `User`, and `AdminUser` can be substituted anywhere `User` is expected without breaking callers (LSP satisfied).

2. **The Template Method Pattern** — an abstract class defines a pipeline skeleton; subclasses fill in specific steps (Lesson 1.2, Example 05). This is legitimate inheritance because the class is designed *for* extension.

3. **Framework extension points** — extending `Controller`, `Migration`, or `TestCase` in frameworks is expected. The framework authors designed these for `extends`.

4. **The chain is at most two levels deep** — `AbstractEntity → UserEntity`. Three or more levels is almost always a smell.

---

## 7 — How Composition Enables Dependency Injection

This is the bridge to Module 3. When you use composition:

```php
class OrderService {
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger
    ) {}
}
```

You have already done the hard work of DI. The constructor signature *is* the dependency declaration. The container in Module 4 reads exactly this signature and wires the dependencies automatically.

When you use inheritance instead:

```php
class OrderService extends PaymentService {  // extends = hardwired coupling
    public function __construct() {
        parent::__construct(); // ← What does this need? Hidden.
    }
}
```

The container cannot wire what it cannot see. Constructor injection makes dependencies visible. Inheritance buries them.

**Composition makes DI possible. Inheritance makes DI impossible.**

---

## 8 — Quick Reference: Composition Decision Guide

```
Q: Do I need behaviour from another class?
   ↓
Q: Does a genuine "is-a" relationship exist AND can the subtype always
   be substituted for the parent without breaking callers?
   YES → Inheritance (max 2 levels deep)
   NO  ↓

Q: Is the behaviour shared across unrelated class hierarchies?
   YES → Trait (horizontal reuse)
   NO  ↓

Q: Is the behaviour optional or swappable?
   YES → Constructor injection (required) or setter injection (optional)
   NO  ↓

Q: Is it a cross-cutting concern (logging, caching, events)?
   YES → Setter injection with Null Object default
   NO  → Method parameter (pass the collaborator per-call)
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 4 (the practical test) and 7 (bridge to DI)
- [ ] Run and study `examples/01-inheritance-vs-composition.php`
- [ ] Run and study `examples/02-deep-inheritance-trap.php`
- [ ] Run and study `examples/03-composing-behaviour.php`
- [ ] Run and study `examples/04-recognising-the-smell.php`
- [ ] Run and study `examples/05-bridge-to-di.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Module 1 complete. Next: **Module 2 — Advanced Types & Enums**, starting with **Lesson 2.0 — LSP**, which explains exactly why deep inheritance trees violate the Liskov Substitution Principle.*