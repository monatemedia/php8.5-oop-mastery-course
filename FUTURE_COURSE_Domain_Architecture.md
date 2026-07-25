# Advanced Domain Architecture & Tactical DDD
### Modelling business reality in PHP 8.5 — aggregates, invariants, events and exception trees

> **Status:** Blueprint. Nothing is built yet. This is the plan for the course that follows
> *PHP 8.5 OOP Mastery*, to be started only once that one is finished.
>
> This supersedes an earlier three-module sketch. What changed and why is recorded at the
> bottom under **Revision notes** — worth reading if you saw the first draft.

---

## Prerequisite

You must have completed **PHP 8.5 OOP Mastery** — all six modules, the capstone, and the
quizzes. Not "read it": completed it, with `php check.php` reporting 30 of 30.

That is not gatekeeping. This course assumes you already know how to compose objects, inject
dependencies, wire a container, and write a test with a fake. Every one of those is a tool you
will use here without explanation. Module 2 of this course is unteachable to someone who has
not internalised Module 3 of that one.

**The shift in subject matter:** the previous course was about *how to wire components*. This
one is about *what the components should be*. It moves from technical objects — loggers,
mailers, repositories — to business objects: an order that knows the rules for cancelling
itself, a price that cannot be negative, a job card that refuses to be invoiced twice.

---

## The one question this whole course answers

> **"If I deleted my framework, my database driver and my web server tomorrow morning, would
> the code in this file still make sense to someone who runs the business?"**

- **Yes** → it belongs in the **domain layer**. Entities, value objects, aggregates, domain
  events, domain exceptions.
- **No** → it belongs in the **infrastructure layer**. Controllers, repository implementations,
  HTTP mapping, database drivers, queue adapters.

Every lesson comes back to this. If you remember nothing else, remember the question.

---

## ⚠️ Read this before you plan anything

**Most applications should not use tactical DDD.** It is a large amount of ceremony that pays
for itself only when the business rules are genuinely complicated. Applied to a CRUD app —
forms in, rows out, no interesting invariants — it produces four layers of indirection around
an `UPDATE` statement and makes the codebase worse.

Module 0 exists to teach you to tell the difference, and it comes first deliberately. A course
that teaches only the patterns produces developers who apply them everywhere. The judgement of
*when not to* is the more valuable half, and it is the half almost every DDD course omits.

---

## 📁 Folder structure

```
~/Herd/php8.5-domain-architecture/
├── README.md                        ← this file
├── PROGRESS.md                      ← your record
├── COURSE_PHILOSOPHY.md             ← the rules, as in the OOP course
├── index.php                        ← cover page (Herd site preview)
├── check.php                        ← where am I? — ported from the OOP course
├── verify.php                       ← is the course intact?
├── run-tests.php                    ← test runner
├── composer.json / composer.lock
├── phpunit.xml
├── domain/                          ← the running example, built up module by module
│   └── workshop/                    ← one domain, carried through the whole course
├── module-0-when-and-when-not/
├── module-1-domain-modelling/
├── module-2-aggregates-and-consistency/
├── module-3-persistence-without-leakage/
├── module-4-domain-exception-trees/
└── module-5-capstone/
```

Each lesson keeps the shape that worked: **README** to read, **examples/** to run,
**challenge/** to complete in `starter.php`, **quiz/** with the answer key at the bottom.

---

## 🗺️ Roadmap

```
[Module 0: When — and When Not — to Do This]
         ↓
[Module 1: Domain Modelling]          value objects, entities, invariants
         ↓
[Module 2: Aggregates & Consistency]  boundaries, transactions, domain events
         ↓
[Module 3: Persistence Without Leakage]  repositories, the mapping problem
         ↓
[Module 4: Domain Exception Trees]    typed failures, API boundary mapping
         ↓
[Module 5: Capstone]                  one domain, end to end
```

---

## The running domain: a vehicle workshop

Every lesson works on **one** domain, built up incrementally: a workshop job-card system.
Module 1 models a `Money` and a `RegistrationNumber`. Module 2 turns `JobCard` into an
aggregate with a lifecycle. Module 3 persists it. Module 4 gives it an exception tree. Module 5
assembles the lot behind an API.

This is deliberate. The main weakness of the OOP Mastery course is that nothing accumulates
until the Module 4 capstone — you complete seventeen standalone exercises and never build
anything. Here, every challenge adds to a codebase you keep.

The domain is chosen because it has genuinely interesting rules: a job card cannot be invoiced
before it is completed, parts cannot be added after invoicing, labour is billed in six-minute
units, a courtesy vehicle cannot be allocated to two jobs at once. Those are real invariants,
not `if ($x === null)`.

---

## Module 0 — When, and When Not, to Do This

*2 lessons · 1 challenge · 2 quizzes*

- [ ] **0.1 — The cost of DDD, and how to price it**
  - What tactical DDD actually costs: indirection, more files, slower onboarding, a mapping
    layer you must maintain.
  - The signals that justify it: invariants spanning multiple entities, a lifecycle with
    illegal transitions, rules the business argues about, calculations with legal consequences.
  - The signals that do not: CRUD, reporting, content management, anything where the database
    schema *is* the model.
  - **Challenge:** given four short system briefs, decide for each whether to use tactical DDD,
    a transaction script, or plain Active Record — and defend each answer in two sentences.

- [ ] **0.2 — Ubiquitous language and bounded contexts**
  - Why the patterns are meaningless without the language. If the code says `Customer` and the
    workshop manager says "account holder", you have already lost.
  - Bounded contexts: the same word meaning different things in different parts of the
    business, and why one giant shared model is the most common DDD failure.
  - Kept deliberately short — this is the strategic minimum needed for the tactical work, not a
    full strategic-DDD treatment.

---

## Module 1 — Domain Modelling

*4 lessons · 4 challenges · 4 quizzes*

- [ ] **1.1 — Rich vs anemic models**
  - The anemic trap: a class of public getters and setters with all the logic in a "service"
    that operates on it. Why that is a procedural program wearing an object costume.
  - Moving behaviour to the data that owns it.
  - *PHP 8.5:* `readonly`, asymmetric visibility (`public private(set)`), constructor promotion.

- [ ] **1.2 — Value objects**
  - Immutability, structural equality, self-validation. Eliminating primitive obsession:
    `Money` instead of `float`, `RegistrationNumber` instead of `string`.
  - Why a value object that can be constructed in an invalid state is not a value object.
  - *PHP 8.5:* `readonly class`, `clone($obj, [...])` for withers, `#[\NoDiscard]` so a
    discarded wither result is caught.
  - **Challenge:** build `Money` and `Currency` with strict arithmetic invariants — no mixed
    currency addition, no floating-point money, no negative construction.

- [ ] **1.3 — Entities and identity**
  - Identity as a continuous thread, independent of attributes. Two customers with identical
    details are two customers; the same job card with every field changed is still that job card.
  - Choosing identity: natural keys, surrogate keys, UUIDs, and who generates them.
  - **Challenge:** model `JobCard` and `Vehicle` as entities with explicit identity, and write
    the equality tests that prove attribute changes do not change identity.

- [ ] **1.4 — Where invariants live**
  - The single most practical lesson in the module. Validation in the constructor, in a
    setter, in a service, at the API boundary — which belongs where, and why "validate
    everywhere" is a design failure.
  - Always-true invariants versus context-dependent rules.
  - **Challenge:** take a class with scattered defensive checks and relocate each one to the
    place that owns it, until the class is impossible to construct in an invalid state.

---

## Module 2 — Aggregates & Consistency

*4 lessons · 4 challenges · 4 quizzes*

- [ ] **2.1 — Aggregate roots and boundaries**
  - Clustering entities and value objects under one root. Nothing outside reaches inside.
  - How to choose a boundary — the question is always "what must be consistent *together, right
    now*?"
  - Why big aggregates are the second most common DDD failure, after the shared model.

- [ ] **2.2 — Transactions: what a boundary actually wraps**
  - An aggregate boundary *is* a transaction boundary. One aggregate per transaction, and what
    to do when that rule is inconvenient.
  - Eventual consistency between aggregates, and why that is a business decision rather than a
    technical one.
  - **Challenge:** split an over-large aggregate into two, and make the cross-aggregate rule
    eventually consistent rather than transactional.

- [ ] **2.3 — Domain events**
  - How aggregates tell the rest of the system what happened without knowing who is listening.
  - Recording events on the aggregate versus dispatching immediately, and why the former is
    almost always right.
  - Connects directly back to the container work: the dispatcher is injected, the handlers are
    auto-wired, and the lifecycle questions from Module 6 of the OOP course all reappear.
  - **Challenge:** make `JobCard` record `JobCardInvoiced`, and wire a handler that sends a
    notification — without the aggregate knowing the notification exists.

- [ ] **2.4 — Domain services**
  - The narrow case: a genuine business operation that belongs to no single entity.
  - Why "I'll put it in a service" is usually a sign the model is anemic, and how to tell a
    real domain service from that mistake.
  - **Challenge:** given three operations, place each correctly — one on an entity, one on a
    value object, one in a domain service — and justify all three.

---

## Module 3 — Persistence Without Leakage

*4 lessons · 4 challenges · 4 quizzes*

- [ ] **3.1 — Repository interfaces belong to the domain**
  - The interface is domain vocabulary (`findOverdueJobCards()`), the implementation is
    infrastructure. The interface lives with the domain; the SQL never does.
  - Repositories return aggregates, not rows, arrays or DTOs.

- [ ] **3.2 — The mapping problem**
  - **The lesson most DDD material skips, and the one that stops people in practice.** A rich
    aggregate with private state and value objects does not map cleanly onto tables.
  - Hydration without public setters. Reconstituting an aggregate from storage without running
    the constructor's invariants a second time. Where an ORM helps and where it fights you.
  - **Challenge:** write a hand-rolled mapper that reconstitutes `JobCard` — with its line
    items and `Money` values — from a flat SQLite result set, with no public setters added.

- [ ] **3.3 — Querying without leaking**
  - Reads that do not fit "load one aggregate": lists, filters, reports. Why forcing those
    through the aggregate is wrong, and what to do instead.
  - Read models and query services as a pragmatic escape hatch. A first honest look at CQRS
    without the ceremony.

- [ ] **3.4 — Testing persistence**
  - In-memory repository implementations for domain tests; real SQLite for integration tests.
  - The contract test: one test suite run against *both* implementations, proving they are
    substitutable — Liskov, cashed in.
  - **Challenge:** write a repository contract test and make both implementations pass it.

---

## Module 4 — Domain Exception Trees

*4 lessons · 4 challenges · 4 quizzes*

- [ ] **4.1 — Infrastructure failures vs domain failures**
  - `PDOException` means the database fell over. `CannotInvoiceIncompleteJobCard` means someone
    tried to break a business rule. Conflating them is why APIs return 500 for user error.
  - Which are exceptional, which are expected, and why that distinction drives the design.

- [ ] **4.2 — Hierarchical exception design**
  - Catch broadly at the boundary, throw narrowly in the core:
    `DomainException` → `JobCardException` → `CannotInvoiceIncompleteJobCard`.
  - Carrying structured context on the exception instead of formatting a message string.
  - **Challenge:** design the exception tree for the workshop domain and refactor scattered
    `\InvalidArgumentException` throws onto it.

- [ ] **4.3 — Exceptions vs result types**
  - When a failure is not exceptional. Returning a result object instead of throwing, and the
    honest trade-offs — exceptions are ergonomic and invisible; results are explicit and noisy.
  - Where each fits, without pretending either is universally correct.

- [ ] **4.4 — Mapping to the API boundary**
  - Catching domain exceptions in middleware and translating them into HTTP without leaking
    stack traces or internal class names.
  - **RFC 9457 Problem Details** — note that this obsoleted RFC 7807 in 2023; older material
    still cites the withdrawn one.
  - **Challenge:** build the translation layer mapping the Module 4.2 tree onto Problem Details
    responses, with a test asserting no internal detail escapes.

---

## Module 5 — Capstone

*1 lesson · 1 challenge · 1 quiz*

- [ ] **5.1 — The workshop, end to end**
  - Assemble everything: the domain model from Module 1, aggregates and events from Module 2,
    persistence from Module 3, the exception tree from Module 4, behind a Slim API using the
    container from the previous course.
  - **Acceptance:** the entire `domain/` directory has zero imports from Slim, PDO, PHP-DI or
    anything else infrastructural. A test proves it by scanning the imports.
  - That test is the course's thesis in executable form.

---

## 📊 Completion table

| Module | Lessons | Challenges | Quizzes | Status |
|--------|---------|-----------|---------|--------|
| 0 — When and When Not | 2 | 1 | 2 | `[ ] Not started` |
| 1 — Domain Modelling | 4 | 4 | 4 | `[ ] Not started` |
| 2 — Aggregates & Consistency | 4 | 4 | 4 | `[ ] Not started` |
| 3 — Persistence Without Leakage | 4 | 4 | 4 | `[ ] Not started` |
| 4 — Domain Exception Trees | 4 | 4 | 4 | `[ ] Not started` |
| 5 — Capstone | 1 | 1 | 1 | `[ ] Not started` |
| **Total** | **19** | **18** | **19** | |

Roughly one challenge per lesson, matching the OOP course's ratio. The earlier draft had three
challenges across nine lessons; that would not have been enough. DDD is judgement, and
judgement comes from repetition.

---

## 🛠️ Target environment

| Item | Value |
|------|-------|
| PHP | **8.5** (same as the OOP course — no version split) |
| Runtime | Laravel Herd, CLI only |
| Location | `~/Herd/php8.5-domain-architecture` — not `htdocs`, nothing is served over HTTP |
| Dependencies | PHPUnit 11, Slim 4 + PHP-DI 7 (capstone only) |
| Domain layer | **Zero framework dependencies.** Enforced by a test, not by good intentions. |

---

## 🔧 Build the verification harness first

**This is the most important instruction in this document.**

The OOP Mastery course was written and never executed. It shipped with reference solutions that
failed their own assertions, expected-output blocks that had never matched reality, a solution
referencing twelve classes it did not declare, and fabricated syntax for a PHP 8.5 feature.
Every one of those survived because nothing ever ran the code.

Do not repeat that. Before writing lesson 0.1:

1. Port `verify.php`, `check.php` and `run-tests.php` from the OOP course.
2. Set up `composer.json`, `phpunit.xml` and `PROGRESS.md`.
3. Confirm `php check.php` runs green against an empty course.

Then, for every lesson, in this order:

1. Write the solution.
2. **Run it.**
3. Paste its *actual* output into `CHALLENGE.md` as the expected output.
4. Derive the starter by removing code from the working solution.
5. Run `php check.php` and confirm the lesson is judged correctly.

Never write an expected-output block by hand. Never write a code sample into a README without
executing it first. That single discipline would have eliminated substantially all of the
remediation work the previous course required.

---

## What this course is for

**After PHP 8.5 OOP Mastery**, you can build systems where swapping MySQL for PostgreSQL, or
SMTP for an API, is a config change rather than a rewrite — and you can prove it with tests.
You understand what a framework's container is doing rather than trusting it.

**After this course**, you can take a tangled set of real business rules — the ones people argue
about in meetings — and express them in code that refuses to represent an invalid state. The
business logic becomes independent of storage and transport, so it can be tested in isolation
and survives the framework being replaced.

That combination matters most where incorrect state is expensive rather than merely annoying:
financial and billing systems with calculation and audit requirements, logistics and workshop
management where a record must stay consistent across many operators, insurance and claims
processing, and regulated domains where "the system allowed it" is not an acceptable answer.

Realistically: this is the skill set that separates a developer who implements specifications
from one who is trusted to *model the problem*. It will not, by itself, make anyone an
architect — that also requires having been wrong at scale a few times. But it is the technical
half of the job, and it is the half you can actually study.

---

## Revision notes

Changes from the earlier three-module draft:

- **Version and path fixed.** The draft said `htdocs/php8.4-domain-architecture` while citing
  PHP 8.5 features throughout. Now 8.5 and Herd, consistently.
- **Challenges tripled** — from 3 across 9 lessons to 18 across 19. One per lesson.
- **Module 0 added.** *When not to do this* now comes first. A DDD course that omits it
  produces developers who apply aggregates to a settings page.
- **Domain events added** (2.3). Tactical DDD without them is incomplete — they are how
  aggregates coordinate without coupling.
- **Transactions added** (2.2). A consistency boundary is meaningless without saying what a
  transaction wraps.
- **The mapping problem added** (3.2). The draft had one repository lesson; the hard part in
  practice is reconstituting a rich aggregate from flat storage, and it stops people cold.
- **Querying and contract testing added** (3.3, 3.4).
- **Result types added** (4.3), so exceptions are presented as a choice rather than the only
  option.
- **RFC corrected.** The draft cited RFC 7807; it was obsoleted by **RFC 9457** in 2023.
- **A single running domain** now threads through every module, so work accumulates instead of
  evaporating after each exercise.
- **Career section rewritten.** The original promised "Principal Architect / Tech Lead". That
  is a claim about a job market, not about a course. Replaced with what the skills let you do
  and where they are valued.
- **Verification harness moved to the front**, for the reasons above.

---

*Park this until the OOP course reads 30 of 30. Then start at Module 0 — and build `check.php`
before you write a single lesson.*
