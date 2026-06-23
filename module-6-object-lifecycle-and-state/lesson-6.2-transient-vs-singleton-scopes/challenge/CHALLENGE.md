# Code Challenge — Lesson 6.2: Transient vs Singleton Scopes in PHP-DI

> **Assign the correct scope to six service classes, prove each choice with a test, and explain your reasoning.**

---

## The Brief

You have joined a team that is registering six services in their PHP-DI container for a new application. Each service class is defined in the starter file. Your job:

1. Decide whether each service should be **singleton** or **transient**
2. Register it in a `SimpleContainer` (provided in the starter) with that scope
3. Write a test that **proves** the scope is correct:
   - For singletons: prove that two resolutions return the same instance AND that sharing the instance across consumers causes no contamination
   - For transients: prove that two resolutions return different instances AND that each starts in the expected initial state
4. Add a one-sentence comment explaining WHY you chose that scope

---

## Prerequisites

- Completed all three examples in this lesson
- Can apply the scope decision rule from README Section 4:
  "Has this class any property written by a public method after construction?"

---

## The Six Services

All six are defined at the top of `starter/ScopeAssignmentTest.php`. Do not modify the service classes.

---

### Service 1 — `CurrencyConverter`

Converts amounts between currencies using a fixed rate table set at construction.

**Your task:**
- Decide: singleton or transient?
- Register it with that scope
- Write `testCurrencyConverterScopeIsCorrect()` proving the scope
- Add a `// SCOPE: singleton|transient — reason` comment

---

### Service 2 — `OrderBuilder`

Accumulates line items for an order in progress. `addLine()` appends to an internal array. `build()` returns the completed order and clears the array.

**Your task:**
- Decide: singleton or transient?
- Register it
- Write `testOrderBuilderScopeIsCorrect()`
- Add the scope comment

---

### Service 3 — `EventDispatcher`

Dispatches domain events to a list of listeners registered at construction time. Listeners are injected; no listener can be added or removed after construction.

**Your task:**
- Decide: singleton or transient?
- Register it
- Write `testEventDispatcherScopeIsCorrect()`
- Add the scope comment

---

### Service 4 — `JobContext`

Stores the current job's metadata: job ID, tenant ID, priority. Set once per job via `initialise()`. Read by other services during job processing.

**Your task:**
- Decide: singleton or transient?
- Register it
- Write `testJobContextScopeIsCorrect()`
- Add the scope comment

---

### Service 5 — `PasswordHasher`

Hashes and verifies passwords using bcrypt. The cost factor is set at construction and never changes. `hash()` and `verify()` are pure computations — no state changes between calls.

**Your task:**
- Decide: singleton or transient?
- Register it
- Write `testPasswordHasherScopeIsCorrect()`
- Add the scope comment

---

### Service 6 — `ReportAccumulator`

Collects report rows across multiple service calls and renders a final report. `addRow()` appends to an internal array; `render()` returns the complete report string.

**Your task:**
- Decide: singleton or transient?
- Register it
- Write `testReportAccumulatorScopeIsCorrect()`
- Add the scope comment

---

## Scope Proof Requirements

**Singleton proof must include:**
- `assertSame($a, $b)` — two resolutions are the same object
- At least one assertion showing that two consumers using the same instance produce correct, independent results (i.e., one consumer's use does not affect another's output)

**Transient proof must include:**
- `assertNotSame($a, $b)` — two resolutions are different objects
- At least one assertion showing that each resolution starts in the expected initial state (empty, null, zero — whatever the class's "blank slate" is)

---

## File Structure

Everything goes in `starter/ScopeAssignmentTest.php`:
- The six service classes are pre-defined at the top (do not modify)
- The `SimpleContainer` helper is provided (do not modify)
- Write registrations and tests inside `ScopeAssignmentTest extends TestCase`

---

## Acceptance Criteria

- [ ] All six services are registered in `setUp()`
- [ ] Each registration has a `// SCOPE: ...` comment
- [ ] `testCurrencyConverterScopeIsCorrect()` — passes with correct identity assertion
- [ ] `testOrderBuilderScopeIsCorrect()` — passes with correct identity assertion
- [ ] `testEventDispatcherScopeIsCorrect()` — passes with correct identity assertion
- [ ] `testJobContextScopeIsCorrect()` — passes with correct identity assertion
- [ ] `testPasswordHasherScopeIsCorrect()` — passes with correct identity assertion
- [ ] `testReportAccumulatorScopeIsCorrect()` — passes with correct identity assertion
- [ ] All singleton tests include a no-contamination assertion
- [ ] All transient tests include a clean-initial-state assertion
- [ ] All tests pass: `./vendor/bin/phpunit`

---

## Running Your Tests

```bash
./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.2-transient-vs-singleton-scopes/challenge/starter/ScopeAssignmentTest.php

# Verbose
./vendor/bin/phpunit --testdox module-6-object-lifecycle-and-state/lesson-6.2-transient-vs-singleton-scopes/challenge/starter/ScopeAssignmentTest.php
```

---

## Expected Output

```
ScopeAssignment
 ✔ Currency converter scope is correct
 ✔ Order builder scope is correct
 ✔ Event dispatcher scope is correct
 ✔ Job context scope is correct
 ✔ Password hasher scope is correct
 ✔ Report accumulator scope is correct

OK (6 tests, N assertions)
```