# Progress Tracker
> PHP 8.5 OOP Mastery Course — the single place you record what you have done.

**How to use this file:** tick a box only when that piece is genuinely finished. For a lesson, "finished" means: you read the README, you *ran* every example and understood the output, you completed the challenge without copying the solution, and you scored above the quiz's own threshold.

Do not start a module until the one before it is fully ticked.

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
| 1.0 SOLID Overview ⭐ | [ ] | [ ] | — | — | — | |
| 1.1 Interfaces | [ ] | [ ] | [ ] | [ ] | ` /18` | |
| 1.2 Abstract Classes & Value Objects | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 1.3 Traits | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 1.4 Composition over Inheritance ⭐ | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 1 gate — can you do all of these from memory?**

- [ ] State all five SOLID principles in one sentence each
- [ ] Explain when to reach for an interface vs an abstract class vs a trait
- [ ] Apply the "can I replace `extends` with a field?" test to real code
- [ ] Write a value object using `readonly` and `clone($obj, [...])`

---

## Module 2 — Advanced Types & Enums

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 2.0 Liskov Substitution Principle | [ ] | [ ] | [ ] | [ ] | ` /18` | |
| 2.1 Type Hinting & Return Types | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 2.2 Property Hooks | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 2.3 Enums | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 2.4 Anonymous Classes | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 2 gate:**

- [ ] Explain covariance and contravariance with your own example
- [ ] Say what makes a property *virtual* vs *backed* without looking it up
- [ ] Replace a set of magic-string constants with a backed enum

---

## Module 3 — Dependency Injection & IoC

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 3.1 Tight vs Loose Coupling | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 3.2 Constructor Injection | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 3.3 Setter & Interface Injection | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 3.4 Inversion of Control | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 3 gate:**

- [ ] Spot every coupling smell in an unfamiliar class in under a minute
- [ ] Explain the difference between DI, DIP and IoC without hedging
- [ ] Justify where the composition root belongs (Golden Rule 1)

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

---

## Module 5 — Automated Testing & TDD

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 5.0 Why Testing Requires DI | [ ] | [ ] | — | — | — | |
| 5.1 PHPUnit Fundamentals | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.2 Fakes and Stubs | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.3 TDD: Red, Green, Refactor | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.4 Integration Testing | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 5.5 Testing Behaviours, Not Layouts | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 5 gate:**

- [ ] `php run-tests.php 5` is all green
- [ ] Name the four test-double types and when each is appropriate
- [ ] Write a failing test *first* without being reminded to

---

## Module 6 — Object Lifecycle & State Management

| Lesson | Read | Ran | Challenge | Quiz | Score | Date |
|--------|:----:|:---:|:---------:|:----:|:-----:|------|
| 6.1 Share-Nothing Architecture | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.2 Transient vs Singleton Scopes | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.3 The Danger of Stateful Services | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.4 Designing Stateless Services | [ ] | [ ] | [ ] | [ ] | ` /20` | |
| 6.5 Factory Definitions | [ ] | [ ] | [ ] | [ ] | ` /20` | |

**Module 6 gate:**

- [ ] `php run-tests.php 6` is all green
- [ ] Decide singleton vs transient for a service and defend the choice
- [ ] Name all five stateful-service anti-patterns from Lesson 6.3

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
