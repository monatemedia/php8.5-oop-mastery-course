# Code Challenge — Lesson 5.3: Test-Driven Development (TDD)

> **Build `PasswordResetService` from scratch — one failing test at a time.**

---

## The Brief

You will build a `PasswordResetService` using strict TDD discipline. There is no pre-written implementation. There is no `PasswordResetService.php` to read first.

You write a test. It fails. You write the minimum code to make it pass. Then the next test. Five cycles. By the end you have a fully implemented service and a test suite that covers every behaviour.

This is the challenge where TDD stops being a concept and becomes a practice.

---

## The Discipline

Follow these rules for every cycle:

1. **Write the test first.** The method being tested must not exist yet.
2. **Run the test.** Confirm it fails (red). If it passes without implementation, the test is wrong — make it stricter.
3. **Write only enough code to make the test pass.** No extra methods, no extra logic.
4. **Run the tests again.** All must be green before moving to the next cycle.
5. **Refactor if needed.** Clean up while keeping tests green.
6. **Move to the next cycle.**

---

## The Five Behaviours

Build the service in this order. Do NOT skip ahead.

---

### Cycle 1 — `generateToken(string $email): string`

**Behaviour:** Returns a 64-character hexadecimal string.

Write a test that verifies:
- The return value is a string
- The return value is exactly 64 characters long
- Two calls return different tokens (not hardcoded)

Then implement `generateToken()`.

**Hint:** `bin2hex(random_bytes(32))` produces 64 hex characters.

---

### Cycle 2 — `storeToken(string $email, string $token): void`

**Behaviour:** Persists the token via a `TokenRepositoryInterface`.

You need to decide what `TokenRepositoryInterface` looks like. Write the anonymous class spy first — that defines the interface. Then extract it.

Write a test that verifies:
- `storeToken()` calls the repository's `store()` method
- The repository receives the correct email and token
- The stored expiry is in the future (relative to the injected clock)

Then implement `storeToken()`.

**The ClockInterface insight:** to test "expiry is in the future", you must inject a clock. The test will not work with `new \DateTimeImmutable()` inline. Let the test force you to add `ClockInterface`.

---

### Cycle 3 — `isTokenValid(string $email, string $token): bool`

**Behaviour:** Returns `true` when the stored token matches and has not expired.

Write a test for the **valid** case:
- Store a token with an expiry 1 hour from the fixed clock's "now"
- Call `isTokenValid()` with that token
- Verify it returns `true`

Then implement the method to pass that test. Then write the invalid cases:
- Wrong token → returns `false`
- Correct token but no record in repo → returns `false`

---

### Cycle 4 — `isTokenValid()` — expired token

**Behaviour:** Returns `false` when the token's expiry is in the past.

The fixed clock is the key: set the clock to a time AFTER the token's expiry.

Write a test:
- Store a token with expiry set to 1 hour ago
- Call `isTokenValid()`
- Verify it returns `false`

Then extend the implementation to check the expiry.

---

### Cycle 5 — `invalidateToken(string $email): void`

**Behaviour:** Marks the token as used so it cannot be re-used.

Write a test:
- Store a valid token
- Verify `isTokenValid()` returns `true`
- Call `invalidateToken()`
- Verify `isTokenValid()` now returns `false`

Then implement `invalidateToken()`.

---

## The Interfaces You Will Need

Define these as formal PHP interfaces in your test file (above the test class). Shape them based on what your anonymous class doubles need.

```php
interface TokenRepositoryInterface
{
    public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void;
    public function find(string $email): ?array; // returns ['token', 'expires_at', 'used'] or null
    public function invalidate(string $email): void;
}

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
```

You may adjust these if your tests demand a different shape — that is fine. The interfaces exist to serve the tests.

---

## The Doubles You Will Need

**Fake TokenRepository** — in-memory store with real find/store/invalidate logic:
```php
$fakeRepo = new class implements TokenRepositoryInterface {
    private array $records = [];
    // store(), find(), invalidate() backed by $this->records
};
```

**Fixed Clock stub** — returns a controlled \DateTimeImmutable:
```php
$fixedClock = new class implements ClockInterface {
    public function now(): \DateTimeImmutable {
        return new \DateTimeImmutable('2026-06-01 12:00:00');
    }
};
```

---

## File Structure

Write everything in `starter/PasswordResetServiceTest.php`:
- Interfaces at the top
- `PasswordResetService` class (grows with each cycle)
- `PasswordResetServiceTest` class at the bottom

---

## Acceptance Criteria

- [ ] The file contains `TokenRepositoryInterface` and `ClockInterface`
- [ ] The file contains a complete `PasswordResetService` class
- [ ] Cycle 1: at least 2 tests for `generateToken()` — all passing
- [ ] Cycle 2: at least 2 tests for `storeToken()` — all passing
- [ ] Cycle 3: at least 2 tests for `isTokenValid()` (valid cases) — all passing
- [ ] Cycle 4: at least 1 test for `isTokenValid()` (expired) — all passing
- [ ] Cycle 5: at least 1 test for `invalidateToken()` — all passing
- [ ] All tests pass: `./vendor/bin/phpunit`
- [ ] You wrote each test BEFORE implementing the method it tests

---

## Running Your Tests

```bash
./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.3-tdd/challenge/starter/PasswordResetServiceTest.php

# Verbose
./vendor/bin/phpunit --testdox module-5-testing-and-tdd/lesson-5.3-tdd/challenge/starter/PasswordResetServiceTest.php
```

---

## Expected Output

```
PasswordResetService
 ✔ Generate token returns a string
 ✔ Generate token returns exactly 64 characters
 ✔ Generate token returns different token on each call
 ✔ Store token calls repository store method
 ✔ Store token stores the correct email and token
 ✔ Store token sets expiry in the future relative to clock
 ✔ Is token valid returns true for matching unexpired token
 ✔ Is token valid returns false for wrong token
 ✔ Is token valid returns false when no record exists
 ✔ Is token valid returns false for expired token
 ✔ Invalidate token makes previously valid token invalid

OK (11 tests, N assertions)
```