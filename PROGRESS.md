# Progress Tracker
> PHP 8.5 OOP Mastery Course — the single place you record what you have done.

**How to use this file:** tick a box only when that piece is genuinely finished. For a lesson, "finished" means: you read the README, you *ran* every example and understood the output, you completed the challenge without copying the solution, and you scored above the quiz's own threshold.

Do not start a module until the one before it is fully ticked.

Each module ends with a **gate** in [`GATES.md`](GATES.md). Half of it covers the module you have
just finished; the other half reaches back to modules you finished a while ago. That second half is
the part that does the work — a concept recalled once, then again three modules later, is held far
longer than one recalled twice in an afternoon. Take the gate with every other file shut.

**Before Lesson 1.0:**

- [ ] `php -v` reports 8.5 or newer
- [ ] `composer install` completed at the course root
- [ ] `php check.php` reports a green environment and intact course
- [ ] Read `COURSE_PHILOSOPHY.md` — the six golden rules

> **Tip:** `php check.php` works out where you are automatically — it walks the challenges in order and stops at the first unsolved one. Use it to confirm what you tick off below.

---

## Legend

| Column | Meaning |
|--------|---------|
| **Read** | Worked through the lesson README |
| **Ran** | Ran every file in `examples/` and understood the output |
| **Challenge** | Completed `starter.php` yourself, then compared against `solution.php` |
| **Quiz** | Took the quiz closed-book and scored above its threshold |
| **Score** | Your quiz score, e.g. `17/20` |

---

## Module 1 — OOP Building Blocks

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 1.0 SOLID Overview ⭐ *(tick the box in its README — `check.php` reads that)* | [ ] | [ ] | — | — | — | |
| 1.1 Interfaces | [ ] | [ ] | [ ] | [ ] | ` /18` | |
| 1.2 Abstract Classes & Value Objects | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 1.3 Traits | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 1.4 Composition over Inheritance ⭐ | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 1 gate — `GATES.md` § Gate 1.** Book shut. Answer from memory, *then* read the key.

- [ ] Part 1 — 4 prompts on Module 1
- [ ] Part 2 — carried forward from Lessons 1.0 and 1.1
- [ ] Every gap logged in *Notes & Sticking Points* at the foot of this file

---

## Module 2 — Advanced Types & Enums

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 2.0 Liskov Substitution Principle | [ ] | [ ] | [ ] | [ ] | ` /18` | |
| 2.1 Type Hinting & Return Types | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 2.2 Property Hooks | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 2.3 Enums | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 2.4 Anonymous Classes | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 2 gate — `GATES.md` § Gate 2.** Book shut. Answer from memory, *then* read the key.

- [ ] Part 1 — 4 prompts on Module 2
- [ ] Part 2 — carried forward from **Module 1** — composition test, immutability, LSP
- [ ] Every gap logged in *Notes & Sticking Points* at the foot of this file

---

## Module 3 — Dependency Injection & IoC

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 3.1 Tight vs Loose Coupling | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 3.2 Constructor Injection | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 3.3 Setter & Interface Injection | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 3.4 Inversion of Control | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 3 gate — `GATES.md` § Gate 3.** Book shut. Answer from memory, *then* read the key.

- [ ] Part 1 — 4 prompts on Module 3
- [ ] Part 2 — carried forward from **Modules 1–2** — SOLID, LSP, enums, value objects
- [ ] Every gap logged in *Notes & Sticking Points* at the foot of this file

---

## Module 4 — Container Automation with PHP-DI

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 4.1 Service Containers (from scratch) | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 4.2 PHP Reflection API | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 4.3 Auto-wiring | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 4.4 PHP-DI Library | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 4.5 Capstone: Slim + PHP-DI ⭐ | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Capstone sub-checklist** — build this in your *own* folder before opening `challenge/`:

- [ ] Domain layer: both repositories + `OrderService`
- [ ] HTTP layer: both controllers, consistent `{success, data}` envelope
- [ ] `config/services.php` — all four interface bindings, nothing else
- [ ] `config/routes.php` — four routes, no logic
- [ ] `public/index.php` — the only place the container is built
- [ ] All 7 request-simulation tests pass
- [ ] `grep -r "getenv\|container->get" src/` returns nothing

**Module 4 gate — `GATES.md` § Gate 4.** Book shut. Answer from memory, *then* read the key.

- [ ] Part 1 — 4 prompts on Module 4
- [ ] Part 2 — carried forward from **Modules 1–3** — SOLID, anonymous classes, IoC, service locator
- [ ] Every gap logged in *Notes & Sticking Points* at the foot of this file

---

## Module 5 — Automated Testing & TDD

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 5.0 Why Testing Requires DI *(tick the box in its README — `check.php` reads that)* | [ ] | [ ] | — | — | — | |
| 5.1 PHPUnit Fundamentals | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.2 Fakes and Stubs | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.3 TDD: Red, Green, Refactor | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.4 Integration Testing | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.5 Testing Behaviours, Not Layouts | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 5 gate — `GATES.md` § Gate 5.** Book shut. Answer from memory, *then* read the key.

- [ ] `php run-tests.php module-5` is all green
- [ ] Part 1 — 4 prompts on Module 5
- [ ] Part 2 — carried forward from **Modules 1–4** — ISP, anonymous classes, DI, containers
- [ ] Every gap logged in *Notes & Sticking Points* at the foot of this file

---

## Module 6 — Object Lifecycle & State Management

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 6.1 Share-Nothing Architecture | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.2 Transient vs Singleton Scopes | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.3 The Danger of Stateful Services | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.4 Designing Stateless Services | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.5 Factory Definitions | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 6 gate — `GATES.md` § Gate 6.** Book shut. Answer from memory, *then* read the key.

- [ ] `php run-tests.php module-6` is all green
- [ ] Part 1 — 4 prompts on Module 6
- [ ] Part 2 — carried forward from **Modules 1, 3, 4, 5** — value objects, injection, scopes, tests
- [ ] Every gap logged in *Notes & Sticking Points* at the foot of this file

---

## Course Complete

- [ ] All six modules ticked
- [ ] Every quiz above its threshold
- [ ] Capstone built from scratch, all 7 tests passing
- [ ] Re-read `COURSE_PHILOSOPHY.md` — do all six rules now feel obvious?

**Started:** ______________  **Finished:** ______________

---

## Notes & Sticking Points

Anything that did not click first time. Being specific here is worth more than any quiz score — these are your re-read targets.

| Date | Lesson | What tripped me up | Resolved? |
|------|--------|--------------------|:---------:|
| | | | [ ] |
| | | | [ ] |
| | | | [ ] |
| | | | [ ] |
| | | | [ ] |
