# PHP 8.5 OOP Mastery Course
### Learn · Code · Quiz — Interactive, fully local, version-locked

> **How to use this README:** This is the map. Work through the modules in order and do not move on until the current one is finished.
>
> Track what you have actually completed in **[`PROGRESS.md`](PROGRESS.md)** — one file, every lesson, challenge and quiz in one place. The checkboxes further down this page are a summary; `PROGRESS.md` is the record.

---

## 🛠️ Local Environment

Everything in this course runs from the **command line**. There is no web server, no Apache, no virtual host. Open a terminal in the course folder and run `php path/to/example.php`.

| Operating System | Recommended Tool | Command to activate PHP 8.5 |
|-----------------|-----------------|------------------------------|
| **Windows / macOS** | [Laravel Herd](https://herd.laravel.com) | `herd use 8.5` |
| **Linux** | [Lerd](https://github.com/geodro/lerd) | `lerd init` → select PHP 8.5 |

**Why Herd rather than XAMPP?** XAMPP bundles PHP as a monolithic install and lags behind new releases — at the time of writing it does not ship PHP 8.5 at all. Herd and Lerd give you one-click version switching: `herd use 8.5` and you are done. No DLL hunting, no `httpd.conf` editing.

> **If this folder lives under `C:\xampp\htdocs\`, that is fine.** The location is irrelevant — nothing here is served over HTTP. What matters is that the `php` on your PATH is 8.5. Verify with `php -v` before you start. If it reports 8.4 or lower, run `herd use 8.5` first.

> ⚠️  Every example requires **PHP 8.5**. Property hooks need 8.4; `clone(...)` with-syntax, `#[\NoDiscard]`, `#[\Override]` on properties, `#[\Deprecated]` on traits/constants and static asymmetric visibility all need 8.5. On PHP 8.4 or below these are parse errors, not warnings.

---

## 📁 Folder Structure

```
php8.5-oop-mastery-course/
├── README.md                      ← You are here
├── COURSE_PHILOSOPHY.md           ← Six golden rules — read before starting
├── PROGRESS.md                    ← Your single progress tracker
├── PHP_VERSION_REFERENCE.md       ← Which features need which PHP version
├── index.php                      ← Course cover page (Herd site preview)
├── check.php                      ← ⭐ The one command to run: where am I?
├── verify.php                     ← Deep integrity check (called by check.php)
├── run-tests.php                  ← Module 5–6 test runner
├── composer.json                  ← Dependencies for Modules 4–6
├── phpunit.xml                    ← PHPUnit config for Module 5–6 tests
└── module-N-.../
    ├── README.md                  ← Module overview + checklist
    └── lesson-N.M-.../
        ├── README.md              ← The lesson itself
        ├── examples/              ← Runnable scripts: php examples/01-....php
        ├── challenge/             ← starter.php (your work) + solution.php
        └── quiz/QUIZ.md           ← Questions + answer key at the bottom
```

Every lesson follows that same four-part shape: **read** the README → **run** the examples → **do** the challenge → **take** the quiz.

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
| **`clone($obj, [...])` "clone with"** | **8.5** | **Lesson 1.2 + Lesson 2.2** |
| **`#[NoDiscard]` attribute** | **8.5** | **Lesson 2.1 + Module 3** |
| **`#[Override]` on properties** | **8.5** | **Lesson 2.0** |
| **`#[\Deprecated]` on constants and traits** *(attribute itself is 8.4)* | **8.5** | **Lesson 1.3** |
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
| 6 — Object Lifecycle | 5 (6.1–6.5) | 5 | 5 | `[ ] Not started` |

---

## 🔧 Project Setup (one-time)

Run these once, from the course root, before starting Module 1.

```bash
# 1. Activate PHP 8.5
herd use 8.5          # Windows/macOS
# or
lerd init             # Linux — select PHP 8.5

# 2. Verify you are on 8.5
php -v
# PHP 8.5.x ...

# 3. Install dependencies (PHP-DI, Slim, PHPUnit — needed from Module 4 on)
composer install

# 4. Check everything — and find out where you are
php check.php
```

### `php check.php` — the only command you need to remember

Run it now, and any time you are unsure what to do next. It works through three phases and stops at the first real problem:

1. **Environment** — confirms PHP 8.5 is active and installs missing Composer dependencies for you.
2. **Integrity** — confirms every course file is intact (this is what `verify.php` does; `check.php` calls it).
3. **Progress** — walks the 28 challenges *in course order* and stops at the first one you have not solved, telling you exactly where you are and what is failing.

Everything before your current lesson is marked done. Everything after is left alone. Solve the one it stops on, run it again, and it moves you forward.

```bash
php check.php                # normal use
php check.php --all          # judge every challenge, do not stop at the first
php check.php --skip-verify  # faster: skip the whole-course syntax sweep
php check.php --no-install   # never run composer install automatically
```

**The other scripts**, if you want them directly:

```bash
php verify.php     # is the COURSE intact? (syntax + PHP 8.4/8.5 feature probes)
php run-tests.php  # run the Module 5 and 6 test suites
php module-1-oop-building-blocks/lesson-1.1-interfaces/examples/01-defining-and-implementing.php
```

> Modules 1–3 need nothing but PHP itself. Composer only matters from Module 4 onwards, but installing up front means you never have to stop mid-lesson.

### The cover page

If you use Herd, this folder is served at a `.test` domain and `index.php` renders a summary of the course, your live environment status and your progress from `PROGRESS.md`. Nothing else in the course is served over HTTP — every lesson is a CLI script.

---

## 📖 Reference

- [`PHP_VERSION_REFERENCE.md`](PHP_VERSION_REFERENCE.md) — every feature this course uses and when it arrived
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