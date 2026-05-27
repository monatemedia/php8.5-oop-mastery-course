# Module 1 — OOP Building Blocks
> **PHP 8.5 OOP Mastery Course**
> **Folder:** `module-1-oop-building-blocks/`

---

## Module Goal

Master the OOP constructs that enforce clean architecture. Understand all five SOLID principles and make the foundational design choice that defines your architectural style: **composition over inheritance**.

By the end of this module you will be able to:

1. Explain every SOLID principle in your own words and point to a code example for each
2. Design and implement interfaces, abstract classes, and traits — and choose the right tool for each situation
3. Recognise when `extends` is appropriate and when it is a composition smell
4. Refactor an inheritance chain to a composed design
5. Explain how composition at the class level is the prerequisite for Dependency Injection at the service level

---

## 🆕 PHP 8.5 Features in This Module

| Feature | Lesson | What it replaces / improves |
|---------|--------|-----------------------------|
| Asymmetric visibility for **static** properties (`public static private(set)`) | **1.1** | Static getters/setters for guarded class state |
| `clone with` syntax for immutable copies | **1.2** | Verbose `wither` methods on readonly/value objects |
| `#[Deprecated]` attribute on **traits** and **constants** | **1.3** | Doc-comment deprecation notices with no enforcement |

---

## 📁 Module Structure

```
module-1-oop-building-blocks/
├── README.md                              ← You are here
├── lesson-1.0-solid-overview/
├── lesson-1.1-interfaces/
├── lesson-1.2-abstract-classes/
├── lesson-1.3-traits/
└── lesson-1.4-composition-over-inheritance/
```

---

## Lesson 1.0 — SOLID Principles Overview ⭐ Start here

> **Read the README and run all five examples before any other lesson.**

### Topics
- **S** — Single Responsibility: one reason to change
- **O** — Open/Closed: open for extension, closed for modification
- **L** — Liskov Substitution: subtypes must honour the parent's contract *(full lesson in 2.0)*
- **I** — Interface Segregation: clients should not depend on methods they do not use
- **D** — Dependency Inversion: depend on abstractions, not concretions *(full treatment in Module 3)*

### Examples
```
examples/
  01-srp.php    ← Single Responsibility
  02-ocp.php    ← Open/Closed
  03-lsp.php    ← Liskov Substitution (preview)
  04-isp.php    ← Interface Segregation
  05-dip.php    ← Dependency Inversion (preview)
```

### Lesson checklist
- [ ] Read the full README — understand what each letter stands for and where it is taught in depth
- [ ] Run all five examples
- [ ] Without looking at the README, write a one-sentence definition of each principle from memory

---

## Lesson 1.1 — Interfaces

> **Contracts that enforce behaviour without implementation.**

### Topics
- What an interface is: a contract, not a class
- Defining and implementing a single interface
- Implementing multiple interfaces *(ISP callout)*
- Interfaces as type hints — polymorphism *(OCP + DIP preview)*
- Interface constants
- Interface inheritance *(ISP callout)*

### PHP 8.4/8.5 additions
- **Asymmetric visibility** (`public private(set)`) for instance properties — PHP 8.4
- **Asymmetric visibility for static properties** (`public static private(set)`) — **PHP 8.5**

```php
// PHP 8.5 — static property readable everywhere, writable only inside the class
class AppConfig {
    public static private(set) string $environment = 'production';

    public static function setEnvironment(string $env): void {
        self::$environment = $env; // ✅ writable inside
    }
}

echo AppConfig::$environment;              // ✅ readable from anywhere
AppConfig::$environment = 'staging';       // ❌ Fatal error — write from outside
AppConfig::setEnvironment('staging');      // ✅ correct way to update
```

### Lesson checklist
- [ ] Defining and implementing a single interface
- [ ] Implementing multiple interfaces
- [ ] Type hints and polymorphism
- [ ] Interface constants
- [ ] Interface inheritance
- [ ] PHP 8.4 asymmetric visibility for instance properties
- [ ] PHP 8.5 asymmetric visibility for static properties
- [ ] **Code Challenge:** Refactor a tightly coupled class to depend on an interface
- [ ] **Quiz:** Interface design and polymorphism

---

## Lesson 1.2 — Abstract Classes & Value Objects

> **Shared implementation with enforced extension points.**

### Topics
- Abstract classes vs interfaces — when to use which
- Defining abstract methods (enforcement) and concrete methods (reuse)
- Constructor logic in abstract classes
- Combining abstract classes with interfaces
- The Template Method Pattern
- Value objects: immutable data containers with `readonly`

### PHP 8.5 addition: `clone with` syntax

Before PHP 8.5, producing an immutable copy of a value object with one changed property required a verbose "wither" method:

```php
// PHP 8.4 — verbose wither method
readonly class Money {
    public function __construct(
        public int    $amountCents,
        public string $currency
    ) {}

    public function withAmount(int $newAmount): static {
        return new static($newAmount, $this->currency); // ← must list all fields
    }
}
```

PHP 8.5 introduces `clone with`:

```php
// PHP 8.5 — clone with: concise immutable copy with targeted change
readonly class Money {
    public function __construct(
        public int    $amountCents,
        public string $currency
    ) {}

    public function withAmount(int $newAmount): static {
        return clone $this with ['amountCents' => $newAmount]; // ← only changed field
    }

    public function withCurrency(string $currency): static {
        return clone $this with ['currency' => $currency];
    }
}

$price    = new Money(29999, 'ZAR');
$adjusted = $price->withAmount(24999);
// $price is unchanged — $adjusted is a new object
```

`clone with` also works on non-readonly classes whenever you want an immutable copy pattern.

### Lesson checklist
- [ ] Abstract methods and concrete methods
- [ ] Constructor logic in abstract classes
- [ ] Combining with interfaces
- [ ] Template Method Pattern
- [ ] Value objects with `readonly`
- [ ] PHP 8.5 `clone with` syntax
- [ ] **Code Challenge:** Extract shared logic into an abstract base; add `clone with` to a value object
- [ ] **Quiz:** Abstract class rules and trade-offs

---

## Lesson 1.3 — Traits

> **Horizontal code reuse across unrelated class hierarchies.**

### Topics
- What traits are and why PHP needs them
- Defining and using a trait
- Multiple traits and conflict resolution (`insteadof`, `as`)
- Trait properties and abstract trait methods
- Traits vs interfaces vs abstract classes

### PHP 8.5 addition: `#[Deprecated]` on traits and constants

PHP 8.5 allows the `#[Deprecated]` attribute to target traits and class constants — not just methods and functions.

```php
// PHP 8.5 — deprecate an entire trait
#[\Deprecated('Use LoggableTrait instead. Will be removed in v3.0.')]
trait LegacyLogTrait {
    public function writeLog(string $msg): void {
        file_put_contents('/tmp/legacy.log', $msg);
    }
}

class OldService {
    use LegacyLogTrait; // ← PHP emits a deprecation notice when this class is instantiated
}

// PHP 8.5 — deprecate a constant
class PaymentStatus {
    #[\Deprecated('Use the PaymentStatus enum instead.')]
    const PENDING = 'pending';   // ← deprecated constant
}
```

This makes deprecation migration paths explicit and machine-enforceable rather than relying on doc-comment conventions.

### Lesson checklist
- [ ] Defining and using a trait
- [ ] Multiple traits and conflict resolution
- [ ] Trait properties and abstract methods
- [ ] Traits with interfaces (the most important real-world pattern)
- [ ] Choosing the right tool (trait vs interface vs abstract class)
- [ ] PHP 8.5 `#[Deprecated]` on traits and constants
- [ ] **Code Challenge:** Extract a cross-cutting concern into a trait
- [ ] **Quiz:** Trait resolution order and conflict rules

---

## Lesson 1.4 — Composition over Inheritance ⭐

> **The foundational design choice that makes Modules 3 and 4 possible.**

### Topics
- What composition means vs inheritance in practice
- Why deep inheritance trees become maintenance nightmares
- The practical test: "Can I replace `extends` with a field?"
- Four PHP composition patterns: constructor injection, setter injection, method parameter, decorator
- When `extends` IS genuinely correct
- Refactoring an inheritance chain to a composed design
- The bridge to Dependency Injection: composition makes DI possible

### Lesson checklist
- [ ] Inheritance vs composition — same problem, both ways
- [ ] The deep inheritance trap
- [ ] Building flexible behaviour with composed collaborators
- [ ] Recognising the composition smell
- [ ] Bridge to DI
- [ ] **Code Challenge:** Refactor a three-level inheritance chain to use composition
- [ ] **Quiz:** Composition vs inheritance trade-offs

---

## ✅ Module 1 Completion Checklist

- [ ] Lesson 1.0 — SOLID Overview (all 5 examples run, definitions written from memory)
- [ ] Lesson 1.1 — Interfaces + PHP 8.5 static asymmetric visibility
- [ ] Lesson 1.2 — Abstract Classes + PHP 8.5 `clone with`
- [ ] Lesson 1.3 — Traits + PHP 8.5 `#[Deprecated]`
- [ ] Lesson 1.4 — Composition over Inheritance

---

*Next module: **Module 2 — Advanced Types & Enums** — starting with **Lesson 2.0 (LSP)**, which explains exactly why deep inheritance trees violate the Liskov Substitution Principle.*