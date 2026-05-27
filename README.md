# PHP 8.5 OOP Mastery Course
### Learn · Code · Quiz — Interactive, fully local, version-locked

> **How to use this README:** Work through each module in order. Tick off `[ ]` checkboxes as you complete each topic. Do **not** move to the next module until all items in the current one are checked.

---

## 🛠️ Local Environment

| Operating System | Recommended Tool | Command to activate PHP 8.5 |
|-----------------|-----------------|------------------------------|
| **Windows / macOS** | [Laravel Herd](https://herd.laravel.com) | `herd use 8.5` |
| **Linux** | [Lerd](https://github.com/geodro/lerd) | `lerd init` → select PHP 8.5 |

**Why not XAMPP?** XAMPP bundles PHP as a monolithic install and lags behind new releases. Laravel Herd and Lerd give you one-click PHP version switching — `herd use 8.5` and you are done. No DLL hunting, no `httpd.conf` editing, no environment pollution.

> ⚠️  Every example in this course requires **PHP 8.5**. Some features (property hooks, `clone with`, `#[NoDiscard]`, static asymmetric visibility) do not exist in earlier versions and will throw a parse error if you run them on PHP 8.5 or below.

---

## 📁 Folder Structure

```
php8.5-oop-mastery-course/
├── README.md                      ← You are here
├── COURSE_PHILOSOPHY.md           ← Six golden rules — read before starting
├── module-1-oop-building-blocks/
│   └── README.md
├── module-2-advanced-types/
│   └── README.md
├── module-3-dependency-injection/
│   └── README.md
├── module-4-container-automation/
│   └── README.md
├── module-5-testing-and-tdd/
│   └── README.md
└── module-6-object-lifecycle-and-state/
    └── README.md
```

---

## 🗺️ Course Roadmap

```
[Module 1: OOP Building Blocks]
         ↓
[Module 2: Advanced Types & Enums]
         ↓
[Module 3: Dependency Injection & IoC]
         ↓
[Module 4: Container Automation with PHP-DI]
         ↓
[Module 5: Automated Testing & TDD]
         ↓
[Module 6: Object Lifecycle & State Management]
```

---

## 🆕 PHP 8.5 Features in This Course

PHP 8.5 introduces several OOP-relevant features that are woven into the appropriate lessons. Here is where each appears:

| Feature | PHP version | Where it appears |
|---------|-------------|------------------|
| Property hooks (`get` / `set`) | 8.4 | Lesson 2.2 |
| Asymmetric visibility for instance properties (`public private(set)`) | 8.4 | Lesson 1.1 |
| **Asymmetric visibility for static properties** | **8.5** | **Lesson 1.1** |
| **`clone with` syntax** | **8.5** | **Lesson 1.2 + Lesson 2.2** |
| **`#[NoDiscard]` attribute** | **8.5** | **Lesson 2.1 + Module 3** |
| **`#[Override]` on properties** | **8.5** | **Lesson 2.0** |
| **`#[Deprecated]` on constants and traits** | **8.5** | **Lesson 1.3** |
| Backed enums | 8.1 | Lesson 2.3 |
| Intersection types | 8.1 | Lesson 2.1 |
| `readonly` properties | 8.1 | Lesson 1.2 |
| Fibers / async (not covered — out of scope) | 8.1 | — |

---

## 🧱 SOLID Principles — Where They Appear

| Principle | Full name | Primary location |
|-----------|-----------|-----------------|
| **S** | Single Responsibility | Lesson 1.0 (overview) · implicit throughout |
| **O** | Open/Closed | Lesson 1.0 · Lesson 1.1 Examples 03 & Challenge |
| **L** | Liskov Substitution | **Lesson 2.0** (full lesson) |
| **I** | Interface Segregation | Lesson 1.0 · Lesson 1.1 Examples 02 & 05 |
| **D** | Dependency Inversion | Lesson 1.0 · **Modules 3 & 4** (full treatment) |

---

## 🏗️ Composition vs Inheritance — A Course-Wide Thread

**Composition over Inheritance** is introduced formally in **Lesson 1.4** and reinforced in every subsequent module:

- **Module 1** → Traits and interfaces as composition tools (Lessons 1.1, 1.3)
- **Module 2** → LSP shows why deep inheritance breaks (Lesson 2.0)
- **Module 3** → DI *is* composition applied to the service graph
- **Module 4** → Containers wire composed graphs automatically
- **Module 5** → Tests prove composed systems are more testable than inherited ones
- **Module 6** → Stateless composed services survive long-running runtimes

---

## Module 1 — OOP Building Blocks
> **Folder:** `module-1-oop-building-blocks/`
> See `module-1-oop-building-blocks/README.md` for full lesson breakdown.

### High-level checklist
- [ ] Lesson 1.0 — SOLID Principles Overview ⭐ Start here
- [ ] Lesson 1.1 — Interfaces *(+ PHP 8.4/8.5: asymmetric visibility)*
- [ ] Lesson 1.2 — Abstract Classes & Value Objects *(+ PHP 8.5: `clone with`)*
- [ ] Lesson 1.3 — Traits *(+ PHP 8.5: `#[Deprecated]` on traits)*
- [ ] Lesson 1.4 — Composition over Inheritance ⭐ New

---

## Module 2 — Advanced Types & Enums
> **Folder:** `module-2-advanced-types/`
> See `module-2-advanced-types/README.md` for full lesson breakdown.

### High-level checklist
- [ ] Lesson 2.0 — LSP *(+ PHP 8.5: `#[Override]` on properties)*
- [ ] Lesson 2.1 — Type Hinting & Return Types *(+ PHP 8.5: `#[NoDiscard]`)*
- [ ] Lesson 2.2 — PHP 8.4/8.5 Property Hooks *(+ PHP 8.5: `clone with` for readonly)*
- [ ] Lesson 2.3 — Enums (PHP 8.1+)
- [ ] Lesson 2.4 — Anonymous Classes

---

## Module 3 — Dependency Injection & IoC
> **Folder:** `module-3-dependency-injection/`
> See `module-3-dependency-injection/README.md` for full lesson breakdown.

### High-level checklist
- [ ] Lesson 3.1 — Tight vs Loose Coupling
- [ ] Lesson 3.2 — Constructor Injection
- [ ] Lesson 3.3 — Setter & Interface Injection
- [ ] Lesson 3.4 — Inversion of Control (IoC)

---

## Module 4 — Container Automation with PHP-DI
> **Folder:** `module-4-container-automation/`
> See `module-4-container-automation/README.md` for full lesson breakdown.

### High-level checklist
- [ ] Lesson 4.1 — Service Containers (build from scratch)
- [ ] Lesson 4.2 — PHP Reflection API
- [ ] Lesson 4.3 — Auto-wiring
- [ ] Lesson 4.4 — PHP-DI Library
- [ ] Lesson 4.5 — Capstone: Slim PHP + PHP-DI ⭐

---

## Module 5 — Automated Testing & TDD
> **Folder:** `module-5-testing-and-tdd/`
> See `module-5-testing-and-tdd/README.md` for full lesson breakdown.

### High-level checklist
- [ ] Lesson 5.0 — Why Testing Requires DI
- [ ] Lesson 5.1 — PHPUnit Fundamentals
- [ ] Lesson 5.2 — Unit Testing with Fakes and Stubs
- [ ] Lesson 5.3 — TDD: Red, Green, Refactor
- [ ] Lesson 5.4 — Integration Testing with a Real Container
- [ ] Lesson 5.5 — Testing Behaviours, Not Layouts

---

## Module 6 — Object Lifecycle & State Management
> **Folder:** `module-6-object-lifecycle-and-state/`
> See `module-6-object-lifecycle-and-state/README.md` for full lesson breakdown.

### High-level checklist
- [ ] Lesson 6.1 — PHP's Share-Nothing Architecture
- [ ] Lesson 6.2 — Transient vs Singleton Scopes in PHP-DI
- [ ] Lesson 6.3 — The Danger of Stateful Services
- [ ] Lesson 6.4 — Designing Stateless Services
- [ ] Lesson 6.5 — Factory Definitions for Complex Lifecycles

---

## ✅ Completion Table

| Module | Lessons | Code Challenges | Quizzes | Status |
|--------|---------|-----------------|---------|--------|
| 1 — OOP Building Blocks | 5 (1.0–1.4) | 4 | 4 | `[ ] Not started` |
| 2 — Advanced Types & Enums | 5 (2.0–2.4) | 5 | 5 | `[ ] Not started` |
| 3 — DI & IoC | 4 (3.1–3.4) | 4 | 4 | `[ ] Not started` |
| 4 — Container Automation | 5 (4.1–4.5) | 5 | 5 | `[ ] Not started` |
| 5 — Testing & TDD | 6 (5.0–5.5) | 5 | 5 | `[ ] Not started` |
| 6 — Object Lifecycle | 5 (6.1–6.5) | 4 | 4 | `[ ] Not started` |

---

## 🔧 Project Setup (one-time)

```bash
# 1. Activate PHP 8.5
herd use 8.5          # Windows/macOS
# or
lerd init             # Linux — select PHP 8.5

# 2. Verify
php -v
# PHP 8.5.x ...

# 3. Clone or create the project folder
mkdir php8.5-oop-mastery-course
cd php8.5-oop-mastery-course

# 4. Install Composer dependencies (from Module 4 onwards)
composer install
```

---

## 📖 Reference

- [PHP 8.5 Migration Guide](https://www.php.net/manual/en/migration85.php)
- [PHP 8.4 Migration Guide](https://www.php.net/manual/en/migration84.php)
- [PHP Manual](https://www.php.net/manual/en/)
- [PHP-DI Documentation](https://php-di.org/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Slim PHP Documentation](https://www.slimframework.com/docs/v4/)
- [Laravel Herd](https://herd.laravel.com)
- [Lerd (Linux)](https://github.com/geodro/lerd)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [PSR-11 Container Interface](https://www.php-fig.org/psr/psr-11/)