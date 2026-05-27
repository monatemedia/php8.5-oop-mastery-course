# Module 3 — Dependency Injection & IoC
> **PHP 8.5 OOP Mastery Course**
> **Folder:** `module-3-dependency-injection/`

---

## Module Goal

Understand why coupling is the enemy of testable code and how to invert control using the patterns that DI containers are built on. By the end of this module you will be able to identify every coupling violation in a codebase, fix it using constructor or setter injection, and understand the Dependency Inversion Principle at an architectural level.

> **Prerequisite:** Lesson 1.4 — Composition over Inheritance. DI *is* composition applied to the service graph. If you have not completed Lesson 1.4, do it now.

By the end of this module you will be able to:

1. Identify every coupling violation in a codebase and name its type
2. Refactor tightly coupled classes to use constructor injection with interface type hints
3. Add optional dependencies via setter injection with Null Object defaults
4. Use the PSR-3 `LoggerAwareInterface` pattern for framework-injected dependencies
5. Explain the Hollywood Principle and the Dependency Inversion Principle
6. Build a manual IoC wiring function from scratch, without a library

---

## 🆕 PHP 8.5 Relevance in This Module

PHP 8.5 introduces no DI-specific features. However, two features from earlier lessons become important here:

| Feature | How it applies in Module 3 |
|---------|---------------------------|
| **`#[NoDiscard]`** (PHP 8.5, Lesson 2.1) | Mark fluent builder methods on services so callers cannot silently discard a reconfigured instance |
| **Interface type hints** (all PHP versions) | The foundation of constructor injection — always type against interfaces, never concrete classes |

The main PHP 8.x features used throughout this module are:
- **Constructor property promotion** (PHP 8.0) — the primary syntax for all injection examples
- **`declare(strict_types=1)`** — required in every file
- **Interface type hints** — every injected dependency is typed against an interface

---

## 📁 Module Structure

```
module-3-dependency-injection/
├── README.md                              ← You are here
├── lesson-3.1-tight-vs-loose-coupling/
├── lesson-3.2-constructor-injection/
├── lesson-3.3-setter-and-interface-injection/
└── lesson-3.4-inversion-of-control/
```

---

## Lesson 3.1 — Tight vs Loose Coupling

> **Learn to read code and find every coupling violation before you fix a single line.**

### Topics
- What coupling means and how to measure it
- The coupling spectrum: content → common → control → data → message
- Why `new ClassName()` inside a constructor is a design smell
- The three costs of tight coupling: untestable, inflexible, hard to swap
- Identifying all coupling violation types: `new-in-constructor`, `concrete-property`, `singleton-access`, `hardcoded-config`, `magic-value`, `god-parameter`

### The five coupling smells
```
□ Does the constructor call `new` on any dependency?
□ Are any property types concrete class names (not interfaces)?
□ Does the class call static methods on concrete classes?
□ Does the class access global state or singletons?
□ Does the class have hard-coded configuration (DSNs, API keys, paths)?
```

### Lesson checklist
- [ ] Coupling spectrum and vocabulary
- [ ] The `new` keyword smell — what it takes away
- [ ] The three costs of tight coupling (untestable, inflexible, hard to swap)
- [ ] Identifying coupling in real code
- [ ] **Code Challenge:** Identify and document all 14 violations in a checkout system
- [ ] **Quiz:** Coupling recognition and consequences

---

## Lesson 3.2 — Constructor Injection

> **The fix for every violation found in Lesson 3.1.**

### Topics
- The DI principle: passing, not creating
- Constructor injection — the preferred pattern
- Type-hinting against interfaces (not concrete classes)
- Constructor property promotion (PHP 8.0) — the standard syntax
- Injecting multiple dependencies cleanly
- The composition root — the only place where `new` is called on services

### The canonical pattern
```php
// ✅ Correct — interface types, no `new` on services
class CheckoutService {
    public function __construct(
        private ProductRepositoryInterface $catalog,
        private InventoryInterface         $inventory,
        private PaymentGatewayInterface    $gateway,
        private LoggerInterface            $logger
    ) {}
}

// Composition root (index.php / bootstrap.php)
$service = new CheckoutService(
    new ProductCatalog($db, $cache, $logger),
    new InventoryChecker($db),
    new StripeGateway(getenv('STRIPE_KEY')),
    new FileLogger(getenv('LOG_PATH'))
);
```

### Lesson checklist
- [ ] The DI principle — passing not creating
- [ ] Constructor injection pattern (full 5-step walkthrough)
- [ ] Type-hinting against interfaces — why concrete types are still coupled
- [ ] Multiple dependencies with property promotion
- [ ] Composition root placement
- [ ] **Code Challenge:** Fix all 14 violations from Lesson 3.1 using constructor injection
- [ ] **Quiz:** DI rules and constructor design

---

## Lesson 3.3 — Setter & Interface Injection

> **Optional dependencies and the PSR-3 LoggerAware pattern.**

### Topics
- Setter injection — optional dependencies
- The Null Object pattern as a safe default (eliminates all `?->` operators)
- Fluent setters (return `static`)
- Interface injection — the `LoggerAwareInterface` + `LoggerAwareTrait` pattern
- PSR-3: the real-world standard for logger injection
- When to use constructor vs setter vs interface injection

### The decision rule
```
Is the dependency REQUIRED for the class to function?
  YES → Constructor injection

Is this a framework/PSR "awareness" contract?
  YES → Interface injection (LoggerAwareInterface + trait)

Is this an optional enhancement with a sensible default?
  YES → Setter injection (NullObject default)
```

### Lesson checklist
- [ ] Setter injection — syntax and use cases
- [ ] Null Object pattern (nullable vs Null Object comparison)
- [ ] Interface injection from scratch
- [ ] PSR-3 `LoggerAwareInterface` + `LoggerAwareTrait`
- [ ] Decision guide: constructor vs setter vs interface
- [ ] **Code Challenge:** Add optional logging/caching/events to InvoiceService
- [ ] **Quiz:** Choosing between injection patterns

---

## Lesson 3.4 — Inversion of Control (IoC)

> **The architectural principle that ties DI together and leads naturally to containers.**

### Topics
- The Hollywood Principle: "Don't call us, we'll call you"
- High-level modules should depend on abstractions, not concretions
- The Dependency Inversion Principle (DIP — the D in SOLID)
- DIP vs DI: DIP is the principle, DI is the technique
- Building a manual IoC wiring function from scratch (no library)
- How manual IoC becomes unwieldy — the motivation for containers (Module 4)

### The DIP in one diagram
```
Without DIP:                    With DIP:
OrderService                    OrderService
  └─ depends on ──► StripeGateway     └─ depends on ──► PaymentGatewayInterface
                                                               ▲
                                                         StripeGateway implements it
```

### Lesson checklist
- [ ] The Hollywood Principle
- [ ] DIP — high-level modules, abstractions, not details
- [ ] DIP vs DI — principle vs technique
- [ ] Manual IoC wiring function
- [ ] The pain of manual wiring at scale → motivation for containers
- [ ] **Code Challenge:** Fully invert a multi-class application's dependencies
- [ ] **Quiz:** IoC vs DI — conceptual differences and real-world application

---

## ✅ Module 3 Completion Checklist

- [ ] Lesson 3.1 — Tight vs Loose Coupling (audit completed)
- [ ] Lesson 3.2 — Constructor Injection (all 14 violations fixed)
- [ ] Lesson 3.3 — Setter & Interface Injection
- [ ] Lesson 3.4 — Inversion of Control

---

*Next module: **Module 4 — Container Automation with PHP-DI** — automate the wiring you just learned to do manually.*