# Code Challenge — Lesson 6.3: The Danger of Stateful Services

> **Identify the anti-pattern, write the test that exposes the bug, and classify the fix.**

---

## The Brief

You are conducting a pre-migration safety audit. The engineering team is moving five services from PHP-FPM to a FrankenPHP worker. You must:

1. **Identify** which of the five anti-patterns each service exhibits
2. **Write a test** that proves the lifecycle bug exists (the test must pass — it asserts that the bug IS present, following the pattern from the examples)
3. **Classify the fix**: for each service, state whether the correct fix is (a) transient scope, (b) external store, or (c) stateless redesign — and explain in one sentence why

The service classes are defined in the starter file. You do not implement the fixes — that is Lesson 6.4. Here you just prove the bugs and classify the remediation.

---

## Prerequisites

- Completed all three examples in this lesson
- Can recognise all five anti-pattern markers from README Section 7

---

## The Five Services

All five are defined in `starter/StatefulServiceAuditTest.php`. Do not modify them.

---

### Service 1 — `SearchIndexBuilder`

Accumulates document IDs as the search index is built. Reports the total indexed at the end. Has `addDocument()` and `getIndexedCount()` methods.

**Your tasks:**
- Name the anti-pattern: which of the five is this?
- Write `testSearchIndexBuilderBug()` — prove the accumulation bug
- Add `// FIX:` comment with your classification and one-sentence reason

---

### Service 2 — `CurrentOperationContext`

Stores the name and start time of the currently executing operation. Set at the start of each job via `beginOperation()`. Read by other services via `getOperationName()` for logging.

**Your tasks:**
- Name the anti-pattern
- Write `testCurrentOperationContextBug()` — prove the context leak
- Add `// FIX:` comment

---

### Service 3 — `BandwidthMonitor`

Counts bytes transferred during the current request. Has `recordBytes()` and `getTotalBytes()`. Used to enforce a per-request bandwidth limit.

**Your tasks:**
- Name the anti-pattern
- Write `testBandwidthMonitorBug()` — prove the counter accumulation
- Add `// FIX:` comment

---

### Service 4 — `FeatureFlagService`

Loads feature flag overrides from a config source on first access. Uses a `$booted` flag to skip re-loading. Once loaded, returns overrides for the current deployment.

**Your tasks:**
- Name the anti-pattern
- Write `testFeatureFlagServiceBug()` — prove the stale-boot bug
- Add `// FIX:` comment

---

### Service 5 — `NotificationQueue`

Queues outgoing notifications (emails, webhooks) as they are generated during request processing. The dispatcher reads the queue at the end of the request and sends everything. Has `enqueue()` and `flush()` methods.

**Your tasks:**
- Name the anti-pattern
- Write `testNotificationQueueBug()` — prove the cross-request contamination
- Add `// FIX:` comment

---

## Test Pattern Reminder

Every bug-proving test follows the same three-act structure:

```php
// ONE instance — simulates singleton
$service = new SomeService();

// Act 1 — "operation 1" uses the service and leaves state behind
$service->doSomething();

// Act 2 — "operation 2" uses the SAME instance — no reset
$service->doSomethingElse();

// Assert — contamination IS present (this proves the bug)
$this->assertSomethingWrong(..., 'BUG: description of what leaked');
```

---

## Fix Classification Options

For each service, choose one:

- **transient scope** — the service class is fine; use `factory()` in PHP-DI so a fresh instance is created per request/job
- **external store** — the state must persist beyond one request (e.g. global totals, audit trails); move it to Redis, a database, or a session store
- **stateless redesign** — eliminate the instance property entirely; accept state as method parameters and return it as values (covered in Lesson 6.4)

Note: more than one fix may be technically correct. Choose the most appropriate given the service's described purpose.

---

## Acceptance Criteria

- [ ] `testSearchIndexBuilderBug()` — passes, proves accumulation across operations
- [ ] `testCurrentOperationContextBug()` — passes, proves context leak
- [ ] `testBandwidthMonitorBug()` — passes, proves counter accumulation
- [ ] `testFeatureFlagServiceBug()` — passes, proves stale-boot
- [ ] `testNotificationQueueBug()` — passes, proves cross-request queue contamination
- [ ] Each test has an anti-pattern name comment (`// ANTI-PATTERN: ...`)
- [ ] Each test has a fix classification comment (`// FIX: ...`)
- [ ] All tests pass: `./vendor/bin/phpunit`

---

## Running Your Tests

```bash
./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.3-danger-of-stateful-services/challenge/starter/StatefulServiceAuditTest.php

# Verbose
./vendor/bin/phpunit --testdox module-6-object-lifecycle-and-state/lesson-6.3-danger-of-stateful-services/challenge/starter/StatefulServiceAuditTest.php
```

---

## Expected Output

```
StatefulServiceAudit
 ✔ Search index builder bug
 ✔ Current operation context bug
 ✔ Bandwidth monitor bug
 ✔ Feature flag service bug
 ✔ Notification queue bug

OK (5 tests, N assertions)
```