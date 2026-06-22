# Code Challenge — Lesson 5.1: PHPUnit Fundamentals

> **Write a complete PHPUnit test suite for the `Money` value object**

---

## The Brief

`Money.php` contains a fully implemented, immutable value object for monetary amounts. Your task is to write a comprehensive test suite for it using PHPUnit.

This challenge is deliberately straightforward — the subject is simple and self-contained. The focus is entirely on **how to write tests**, not on complex domain logic. By the time you finish, you should be able to structure a test class, use every core assertion type, test exceptions correctly, and use setUp() for isolation.

---

## Prerequisites

PHPUnit must be installed:

```bash
composer require --dev phpunit/phpunit
```

---

## What `Money` Does

Read `Money.php` carefully before writing a single test. Understand:

1. What the constructor accepts and what it rejects
2. What each method returns
3. What each method throws and when
4. That `Money` is **immutable** — every arithmetic method returns a NEW instance

---

## Your Tasks

Work in `starter/MoneyTest.php`. Do NOT look at `solution/MoneyTest.php` until you have made a genuine attempt.

### Task 1 — Constructor: valid inputs

Write tests that verify:
- A `Money` with `amountCents: 29999` and `currency: 'ZAR'` is created successfully
- The `amountCents` property equals the value passed in
- The `currency` property equals the value passed in
- Zero cents is a valid amount (`new Money(0, 'ZAR')` does not throw)

### Task 2 — Constructor: invalid inputs

Write tests that verify:
- Negative `amountCents` throws `\InvalidArgumentException`
- A currency shorter than 3 letters throws `\InvalidArgumentException`
- A currency longer than 3 letters throws `\InvalidArgumentException`
- A lowercase currency like `'zar'` throws `\InvalidArgumentException`
- The exception message for negative amounts contains `'non-negative'`

### Task 3 — `add()`

Write tests that verify:
- Adding two `Money` objects with the same currency returns a new `Money` with the summed amount
- The original objects are unchanged after `add()` (immutability)
- Adding zero to a `Money` returns the same amount
- Adding Money of different currencies throws `\InvalidArgumentException`

### Task 4 — `subtract()`

Write tests that verify:
- Subtracting a smaller amount from a larger one returns the correct difference
- Subtracting equal amounts returns zero
- Subtracting a larger amount from a smaller one throws `\InvalidArgumentException`
- The exception message contains `'negative'`
- Subtracting different currencies throws `\InvalidArgumentException`

### Task 5 — `multiplyBy()`

Write tests that verify:
- Multiplying by 2 doubles the amount
- Multiplying by 0.5 halves the amount (rounded to nearest cent)
- Multiplying by 0 returns zero cents
- Multiplying by a negative factor throws `\InvalidArgumentException`
- The result is a new `Money` instance with the same currency

### Task 6 — Comparison methods

Write tests that verify:
- `equals()` returns true for two `Money` objects with same amount and currency
- `equals()` returns false when amounts differ
- `equals()` returns false when currencies differ (even with same amount)
- `isGreaterThan()` returns true when this amount exceeds the other
- `isGreaterThan()` returns false when this amount is less than or equal to the other
- `isLessThan()` works correctly
- `isZero()` returns true for zero cents, false otherwise
- Cross-currency comparison throws `\InvalidArgumentException`

### Task 7 — `format()`

Write tests that verify:
- `format()` returns `'ZAR 299.99'` for `new Money(29999, 'ZAR')`
- `format()` returns `'USD 0.00'` for `new Money(0, 'USD')`
- `format()` returns `'EUR 1000.00'` for `new Money(100000, 'EUR')`

### Task 8 — Immutability

Write a test that verifies:
- After calling `add()`, the original `Money` instance is unchanged
- After calling `subtract()`, the original `Money` instance is unchanged
- After calling `multiplyBy()`, the original `Money` instance is unchanged

---

## Acceptance Criteria

- [ ] Test class extends `PHPUnit\Framework\TestCase`
- [ ] All test methods are `public` and named `test*` (or use `#[Test]`)
- [ ] `setUp()` is used to create at least one reusable `Money` instance
- [ ] All 7 task groups have at least one test each
- [ ] Exception tests use `expectException()` BEFORE the throwing call
- [ ] At least one test uses `expectExceptionMessage()`
- [ ] At least one test uses a `DataProvider`
- [ ] All tests pass when run with `./vendor/bin/phpunit`
- [ ] Test names clearly describe the behaviour being tested

---

## Running Your Tests

```bash
# Run only this challenge
./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/challenge/starter/MoneyTest.php

# Run with readable output
./vendor/bin/phpunit --testdox module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/challenge/starter/MoneyTest.php
```

---

## Expected Output (abridged)

```
Money
 ✔ Constructor creates money with valid amount and currency
 ✔ Constructor stores amount cents correctly
 ✔ Constructor stores currency correctly
 ✔ Zero cents is a valid amount
 ✔ Negative amount throws invalid argument exception
 ✔ Exception message mentions non-negative for negative amount
 ...
 ✔ Format returns correct string for non-zero amount
 ✔ Add result is immutable — original unchanged

OK (N tests, N assertions)
```