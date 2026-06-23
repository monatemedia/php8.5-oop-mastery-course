# Code Challenge — Lesson 6.1: PHP's Share-Nothing Architecture

> **Audit five service classes for lifecycle safety — write tests that catch the bugs, then propose fixes.**

---

## The Brief

You are the new backend engineer at a company that is migrating its monolith from PHP-FPM to FrankenPHP worker mode. The existing codebase was written assuming share-nothing — every class was designed for a per-request lifetime. The migration means these classes will now live for the entire worker lifetime.

Your job: audit five services, write a test for each that **demonstrates the lifecycle bug by simulating worker reuse**, and propose a one-sentence fix for each.

You do not implement the fixes in this challenge — that is Lesson 6.4. Here you just prove the bugs exist.

---

## Prerequisites

- Completed `examples/01-share-nothing-demo.php`
- Completed `examples/02-long-running-worker.php`
- Understand: a test that simulates worker reuse creates **one service instance** and calls it multiple times, asserting that the second call sees contamination from the first

---

## The Five Services to Audit

All five are defined at the top of `starter/ShareNothingAuditTest.php`. Do not modify the service classes themselves — only write tests and fix proposals.

---

### Service 1 — `BasketService`

Accumulates items added during a shopping session. Used as a singleton across requests.

**Your task:**
- Write `testBasketAccumulatesItemsAcrossRequests()` — show that items added during "request 1" are still present in "request 2"
- Write `testBasketCountIsAlwaysOneForFreshRequests()` — show what the CORRECT behaviour would be if the object were fresh (i.e., what the service would do under share-nothing)

---

### Service 2 — `AuditLogger`

Records audit events for the current operation. Reports a summary at the end. Used as a singleton.

**Your task:**
- Write `testAuditLoggerAccumulatesEntriesAcrossOperations()` — show that operation 2's audit log contains entries from operation 1
- Write `testAuditLoggerSummaryIsCorruptedByPreviousEntries()` — show that the summary count is wrong for operation 2

---

### Service 3 — `UserSessionService`

Stores the currently authenticated user. Other services call `getCurrentUser()` to find out who is logged in. Used as a singleton.

**Your task:**
- Write `testUserSessionLeaksAcrossRequests()` — show that after user Alice logs in during request 1, the service still reports Alice as the current user during request 2 (even though request 2 has no authenticated user)
- Write `testUnauthenticatedRequestShouldReturnNullUser()` — document the expected behaviour (null) that the bug violates

---

### Service 4 — `RateLimiter`

Counts API hits per key. Intended as a per-request counter (to count how many times the current request called a downstream API). Used as a singleton across requests.

**Your task:**
- Write `testRateLimiterCountsAccumulateAcrossRequests()` — show that hits from request 1 push the count over the limit for request 2, even though request 2 made only a single hit

---

### Service 5 — `ReportBuilder`

Collects report rows and renders them to a string. Used as a singleton across requests.

**Your task:**
- Write `testReportBuilderIncludesRowsFromPreviousRequests()` — show that report 2 contains rows that were added during report 1
- Write `testReportBuilderRowCountIsWrongForSecondRequest()` — assert the wrong count explicitly so the bug is unmistakable

---

## Fix Proposals

After each test group, add a comment block:

```php
/*
 * FIX PROPOSAL for ServiceName:
 * [One sentence describing the correct fix]
 */
```

Examples of well-formed fix proposals:
- "Make the service stateless: accept the items list as a method parameter and return a new total rather than accumulating on the object."
- "Use transient scope in the DI container so a fresh instance is created for each request."
- "Move authentication state to a per-request RequestContext object injected via a transient-scoped factory."

---

## File Structure

Everything goes in `starter/ShareNothingAuditTest.php`:
- The five service classes are pre-defined at the top (do not modify them)
- Write your test methods inside `ShareNothingAuditTest extends TestCase`
- Add fix proposal comments after each test group

---

## Acceptance Criteria

- [ ] `testBasketAccumulatesItemsAcrossRequests()` — fails only if the object is recreated per call; passes when the same instance is reused
- [ ] `testBasketCountIsAlwaysOneForFreshRequests()` — passes, documenting correct behaviour
- [ ] `testAuditLoggerAccumulatesEntriesAcrossOperations()` — catches cross-operation contamination
- [ ] `testAuditLoggerSummaryIsCorruptedByPreviousEntries()` — wrong count caught
- [ ] `testUserSessionLeaksAcrossRequests()` — Alice is still set after request 1
- [ ] `testUnauthenticatedRequestShouldReturnNullUser()` — expected null behaviour documented
- [ ] `testRateLimiterCountsAccumulateAcrossRequests()` — request 2 is incorrectly rate-limited
- [ ] `testReportBuilderIncludesRowsFromPreviousRequests()` — rows from report 1 appear in report 2
- [ ] `testReportBuilderRowCountIsWrongForSecondRequest()` — explicit count assertion
- [ ] All five fix proposal comments are present
- [ ] All tests pass: `./vendor/bin/phpunit`

---

## Running Your Tests

```bash
./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.1-share-nothing-architecture/challenge/starter/ShareNothingAuditTest.php

# Verbose
./vendor/bin/phpunit --testdox module-6-object-lifecycle-and-state/lesson-6.1-share-nothing-architecture/challenge/starter/ShareNothingAuditTest.php
```

---

## Expected Output

```
ShareNothingAudit
 ✔ Basket accumulates items across requests
 ✔ Basket count is always one for fresh requests
 ✔ Audit logger accumulates entries across operations
 ✔ Audit logger summary is corrupted by previous entries
 ✔ User session leaks across requests
 ✔ Unauthenticated request should return null user
 ✔ Rate limiter counts accumulate across requests
 ✔ Report builder includes rows from previous requests
 ✔ Report builder row count is wrong for second request

OK (9 tests, N assertions)
```