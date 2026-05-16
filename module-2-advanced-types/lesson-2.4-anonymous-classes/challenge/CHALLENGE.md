# Code Challenge — Lesson 2.4: Anonymous Classes
> **Replace named test double files with inline anonymous class stubs**
> This is also the **Module 2 capstone** — it touches enums, type hints, and interfaces from earlier lessons.

---

## The Brief

You have a small order processing system with a `PaymentProcessor` class that depends on three interfaces: `PaymentGateway`, `Logger`, and `AuditStore`. The codebase has a dedicated test double file — `TestDoubles.php` — with three named classes (`FakeGateway`, `FakeLogger`, `FakeAuditStore`) that exist solely for testing.

Your job is to:
1. Delete (or ignore) `TestDoubles.php` — it should not exist.
2. Replace each named test double with an anonymous class stub defined inline.
3. Apply Module 2 knowledge throughout: strict types, enums for status, and proper interface contracts.

---

## What the Starter Code Has

Open `starter.php`. You will find:

**The production interfaces and classes:**
- `PaymentStatus` — a string-backed enum with cases `Pending`, `Success`, `Failed`, `Refunded`
- `PaymentGateway` interface — `charge(float $amount, string $currency, string $token): PaymentStatus`
- `Logger` interface — `log(string $level, string $message): void`
- `AuditStore` interface — `record(string $event, array $context): void` and `getEntries(): array`
- `PaymentProcessor` class — the system under test; depends on all three

**The named test doubles (to be replaced):**
```php
class FakeGateway implements PaymentGateway { ... }    // Always returns Success
class FakeLogger implements Logger { ... }             // Records log entries
class FakeAuditStore implements AuditStore { ... }     // Records audit events
```

**Five test functions** that use the named doubles.

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Delete or comment out all named test double classes
Remove `FakeGateway`, `FakeLogger`, and `FakeAuditStore` from the file.

### Task 2 — Rewrite `testSuccessfulCharge()` with anonymous class stubs
Define three anonymous classes inline inside the test function:
- A gateway stub that always returns `PaymentStatus::Success`
- A spy logger that records all entries into a public `$entries` array
- A spy audit store that records all events into a public `$events` array

Assert that after `$processor->charge(...)`:
- The logger has exactly 2 entries
- The audit store has exactly 1 event with `event = 'payment.charged'`
- The returned status is `PaymentStatus::Success`

### Task 3 — Rewrite `testFailedCharge()` with anonymous class stubs
Define a gateway stub that always returns `PaymentStatus::Failed`. Use null object anonymous classes for logger and audit store (they do nothing, but satisfy the interface). Assert the returned status is `PaymentStatus::Failed`.

### Task 4 — Rewrite `testRefund()` with anonymous class stubs
Define a gateway stub that always returns `PaymentStatus::Refunded`. Spy on the audit store. Assert there is exactly 1 audit event with `event = 'payment.refunded'`.

### Task 5 — Rewrite `testLoggerCaptures()` with an anonymous class stub
Create a spy logger stub. Run a charge. Assert the logger captured an entry containing the word `"charged"`.

### Task 6 — Rewrite `testInvalidToken()` with anonymous class stubs
Define a gateway stub that throws `\InvalidArgumentException` when charged with an empty token. Assert the exception is caught and the logger records an `'ERROR'` entry.

---

## Acceptance Criteria

- [ ] No named test double classes remain in the file (`FakeGateway`, `FakeLogger`, `FakeAuditStore` are gone)
- [ ] All five test functions define their dependencies as anonymous classes
- [ ] `PaymentStatus` enum is used throughout — no raw strings for status values
- [ ] All anonymous class stubs implement the correct interface
- [ ] All five tests print `PASS`
- [ ] The production code (`PaymentProcessor`, interfaces, `PaymentStatus`) is unchanged

---

## Expected Output

```
testSuccessfulCharge ... PASS
testFailedCharge ....... PASS
testRefund ............. PASS
testLoggerCaptures ..... PASS
testInvalidToken ....... PASS
All 5 tests passed.
```