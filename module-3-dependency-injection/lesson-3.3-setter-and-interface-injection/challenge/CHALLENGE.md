# Code Challenge — Lesson 3.3: Setter & Interface Injection

> **Add optional dependencies to a service using setter injection and the Null Object pattern**

---

## The Brief

You have a working `InvoiceService` that uses constructor injection for its required dependencies. Your task is to add three optional dependencies to it using setter injection, with proper Null Object defaults for each. You will also implement interface injection for the logger using the `LoggerAwareInterface` pattern.

---

## What the Starter Code Has

Open `starter.php`. You will find:

- `InvoiceService` — a service that generates invoices, processes payments, and sends confirmations
- It correctly uses constructor injection for `DatabaseInterface` and `PaymentGatewayInterface`
- It has **no logging**, **no caching**, and **no event dispatching** — these need to be added as optional deps

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Create Null Object implementations
Create these three Null Object classes:
- `NullLogger implements LoggerInterface` — silent `log()` method
- `NullCache implements CacheInterface` — `get()` returns `null`, `set()` and `has()` do nothing
- `NullDispatcher implements EventDispatcherInterface` — silent `dispatch()` method

### Task 2 — Add interface injection for Logger
Define `LoggerAwareInterface` with `setLogger(LoggerInterface $logger): void`.
Create `LoggerAwareTrait` that implements it (stores logger in `protected LoggerInterface $logger`).
Make `InvoiceService implement LoggerAwareInterface` and `use LoggerAwareTrait`.
In the `InvoiceService` constructor, default `$this->logger = new NullLogger()`.

### Task 3 — Add setter injection for Cache
Add `private CacheInterface $cache` to `InvoiceService`.
Default it to `new NullCache()` in the constructor.
Add `public function setCache(CacheInterface $cache): static` (fluent — returns `static`).
Use `$this->cache` inside `generate()` to cache generated invoices by ID.

### Task 4 — Add setter injection for EventDispatcher
Add `private EventDispatcherInterface $dispatcher` to `InvoiceService`.
Default it to `new NullDispatcher()` in the constructor.
Add `public function setDispatcher(EventDispatcherInterface $dispatcher): static`.
Dispatch `'invoice.generated'` inside `generate()` and `'invoice.paid'` inside `processPayment()`.

### Task 5 — Update `generate()` and `processPayment()` to use all three deps
- `generate()`: log INFO at start and end, cache the result, dispatch `invoice.generated`
- `processPayment()`: log INFO at start and end, dispatch `invoice.paid` on success

### Task 6 — Wire three different contexts at the composition root
**Context 1: Minimal** — only required deps, all optional deps stay as Null Objects.
**Context 2: Full production** — inject all three optional deps with real implementations.
**Context 3: Test** — spy logger and spy dispatcher to assert on calls.

---

## Acceptance Criteria

- [ ] `NullLogger`, `NullCache`, `NullDispatcher` defined
- [ ] `LoggerAwareInterface` + `LoggerAwareTrait` defined
- [ ] `InvoiceService` implements `LoggerAwareInterface` and uses `LoggerAwareTrait`
- [ ] `InvoiceService` constructor defaults all optional deps to Null Objects
- [ ] `setCache()` and `setDispatcher()` are fluent (return `static`)
- [ ] `generate()` logs, caches, and dispatches — all via direct calls (no `?->`)
- [ ] `processPayment()` logs and dispatches on success
- [ ] Context 1 produces output with no log, cache, or event lines
- [ ] Context 2 produces output with log, cache, and event lines
- [ ] Context 3 spy assertions all pass

---

## Expected Output

```
=== Context 1: Minimal (Null Objects) ===
Invoice #INV-001 generated. Total: R1499.98
Payment for #INV-001: success

=== Context 2: Full production ===
  [INFO] Generating invoice #INV-002
  [CACHE] MISS: invoice:INV-002
  [INFO] Invoice #INV-002 generated. Total: R2999.97
  [CACHE] SET: invoice:INV-002
  [EVENT] invoice.generated: {"id":"INV-002","total":2999.97}
  [INFO] Processing payment for #INV-002
  [INFO] Payment for #INV-002: success
  [EVENT] invoice.paid: {"id":"INV-002"}

=== Context 3: Test (spy assertions) ===
  Spy logger entries: 4
  Spy dispatcher events: 2
  First event: invoice.generated
  All assertions PASSED
```