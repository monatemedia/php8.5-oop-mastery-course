# Code Challenge — Lesson 6.4: Designing Stateless Services

> **Refactor five stateful services from Lesson 6.3 into stateless equivalents, then prove the bug is gone.**

---

## The Brief

You are completing the migration audit started in Lesson 6.3. You have identified the bugs; now you fix them. For each of the five services:

1. **Refactor** the service to eliminate the instance state that caused the bug
2. **Write a test proving the bug is gone** — simulate singleton reuse and assert the contamination no longer occurs
3. **Write a test proving the refactored service produces correct results** — the fix must not break the service's original purpose

The service classes from Lesson 6.3 are reproduced in the starter file. Refactor them in-place.

---

## Prerequisites

- Completed the Lesson 6.3 challenge (you know which anti-pattern each service has)
- Read README Sections 2–5 (each covers one refactoring move)

---

## The Five Services to Refactor

---

### Service 1 — `SearchIndexBuilder` (Anti-pattern 1: Accumulating array)

**Current state:** `private array $documentIds = []` appended by `addDocument()`.

**Refactoring move:** Make `addDocument()` accept and return the document list. The caller accumulates in their own variable. Remove `$documentIds` from the class.

**Tests to write:**
- `testSearchIndexBuilderBugIsGone()` — same instance, two indexing runs, assert the second run sees only its own documents
- `testSearchIndexBuilderProducesCorrectOutput()` — correct document list and count for a given set of inputs

---

### Service 2 — `CurrentOperationContext` (Anti-pattern 3: Request-scoped data)

**Refactoring move:** Eliminate `beginOperation()` / `endOperation()` methods entirely. Replace the class with an immutable value object: all fields set at construction via `readonly` properties, no public setters.

**Tests to write:**
- `testCurrentOperationContextBugIsGone()` — prove that two context objects for different operations are independent (changing one does not affect the other)
- `testCurrentOperationContextCarriesCorrectData()` — verify the context carries the correct name and start time after construction

---

### Service 3 — `BandwidthMonitor` (Anti-pattern 4: Counter on singleton)

**Refactoring move:** Make `recordBytes()` accept the current total and return the new total. Remove `$totalBytes` from the class. The caller owns and threads the running total.

**Tests to write:**
- `testBandwidthMonitorBugIsGone()` — same instance, two request simulations, each starting from 0; assert no carryover
- `testBandwidthMonitorEnforcesLimitCorrectly()` — verify `isOverLimit()` and `getRemainingBytes()` are correct for various totals

---

### Service 4 — `FeatureFlagService` (Anti-pattern 5: Boolean latch)

**Refactoring move:** Eliminate the `$booted` flag and `boot()` method. Load flags eagerly in the constructor. A new instance with new config is the mechanism for updating flags — not calling `boot()` again.

**Tests to write:**
- `testFeatureFlagServiceBugIsGone()` — prove that a new instance with updated config reflects the update immediately, with no boot() call required
- `testFeatureFlagServiceReturnsCorrectFlags()` — verify `isEnabled()` returns the correct value for flags that are true, false, and absent

---

### Service 5 — `NotificationQueue` (Anti-pattern 1: Accumulating array, variant)

**Refactoring move:** Make `enqueue()` accept a notifications array and return a new array with the notification appended. Remove `$pending` from the class. `flush()` becomes a pure function that takes the pending array and returns it (the caller resets their variable after flush).

**Tests to write:**
- `testNotificationQueueBugIsGone()` — same instance, two request simulations; notifications from request 1 cannot appear in request 2's flush
- `testNotificationQueueEnqueueAndFlushWorkCorrectly()` — verify the full enqueue → flush cycle produces the correct notifications

---

## Acceptance Criteria

- [ ] `SearchIndexBuilder` has no `$documentIds` property after refactor
- [ ] `CurrentOperationContext` has only `readonly` properties after refactor
- [ ] `BandwidthMonitor` has no `$totalBytes` property after refactor
- [ ] `FeatureFlagService` has no `$booted` property and no `boot()` method after refactor
- [ ] `NotificationQueue` has no `$pending` property after refactor
- [ ] All ten tests pass: `./vendor/bin/phpunit`

---

## Running Your Tests

```bash
./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.4-designing-stateless-services/challenge/starter/StatelessRefactorTest.php

# Verbose
./vendor/bin/phpunit --testdox module-6-object-lifecycle-and-state/lesson-6.4-designing-stateless-services/challenge/starter/StatelessRefactorTest.php
```

---

## Expected Output

```
StatelessRefactor
 ✔ Search index builder bug is gone
 ✔ Search index builder produces correct output
 ✔ Current operation context bug is gone
 ✔ Current operation context carries correct data
 ✔ Bandwidth monitor bug is gone
 ✔ Bandwidth monitor enforces limit correctly
 ✔ Feature flag service bug is gone
 ✔ Feature flag service returns correct flags
 ✔ Notification queue bug is gone
 ✔ Notification queue enqueue and flush work correctly

OK (10 tests, N assertions)
```