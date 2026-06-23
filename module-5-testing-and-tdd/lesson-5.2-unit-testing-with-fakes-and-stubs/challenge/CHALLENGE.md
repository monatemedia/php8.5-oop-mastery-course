# Code Challenge — Lesson 5.2: Unit Testing with Fakes and Stubs

> **Write a full unit test suite for `OrderService` using anonymous class test doubles**

---

## The Brief

`OrderService.php` contains a complete `OrderService` class with three injected dependencies. Your task is to write a comprehensive unit test suite that covers every path through the service — using only anonymous class test doubles. No mocking framework.

This challenge puts the lesson's four double types to work simultaneously:

| Double | Used for |
|--------|----------|
| Fake | `ProductRepositoryInterface` — needs to actually look up products |
| Stub | `PaymentGatewayInterface` — controls whether payment succeeds or fails |
| Spy  | `MailerInterface` — verifies emails are sent with the right arguments |
| Null Object | Any dependency irrelevant to a given test |

---

## Prerequisites

Read `OrderService.php` end-to-end before writing a single test. Understand:
- All three constructor dependencies and their interfaces
- The three paths through `placeOrder()`: success, payment declined, product not found
- What `placeOrder()` returns on each path
- What `placeOrder()` throws on each path
- What side effects occur (and on which dependency)

---

## Your Tasks

Work in `starter/OrderServiceTest.php`. Do NOT open `solution/OrderServiceTest.php` until you have made a genuine attempt.

---

### Task 1 — Set up the test class and shared doubles

In `setUp()`:
- Create a fake `ProductRepositoryInterface` that returns a product with `id: 1`, `name: 'Widget Pro'`, `price: 29999`, `sku: 'WDG-001'` for `findById(1)` and `null` for any other ID
- Create a spy `MailerInterface` that records every `send()` call in a public array
- Create a stub `PaymentGatewayInterface` that always returns `true`
- Construct `OrderService` using all three

### Task 2 — Success path

Write tests that verify:
- `placeOrder()` returns `['success' => true]` when all dependencies succeed
- The returned `order_id` is a non-null integer
- The returned `total_cents` equals `product.price × qty` (e.g. 29999 × 2 = 59998)
- The `error` key is `null` on success
- Exactly one email is sent on the success path
- The email is sent to the correct recipient (`$customerEmail`)
- The email subject contains the product name

### Task 3 — Payment declined path

For this task, create an inline failing stub inside the test (override the default gateway):
- `placeOrder()` returns `['success' => false]`
- The `error` key contains `'Payment declined'`
- The `order_id` is `null`
- **No email is sent** when payment is declined (use your spy to verify)

### Task 4 — Product not found path

- `placeOrder()` throws `\DomainException` when the product ID does not exist
- The exception message mentions the product ID
- **No email is sent** when the product is not found

### Task 5 — Gateway throws (infrastructure failure)

Create an inline throwing stub:
- When the gateway throws `\RuntimeException`, `placeOrder()` propagates it
- **No email is sent** before the exception escapes

### Task 6 — `calculateTotal()`

- Returns `product.price × qty` for a valid product ID
- Throws `\DomainException` for an unknown product ID

### Task 7 — Email content verification

Using the spy:
- The email body contains the product name
- The email body contains the total formatted as a currency amount (e.g. `R299.99`)
- The email body contains the quantity ordered

---

## Acceptance Criteria

- [ ] Test class extends `TestCase`
- [ ] `setUp()` creates fresh doubles and a fresh `OrderService` before every test
- [ ] All four double types appear: fake (repo), stub (gateway), spy (mailer), null object (where applicable)
- [ ] Task 2 (success path) has at least 5 test methods
- [ ] Task 3 (declined) has at least 2 test methods
- [ ] Task 4 (not found) has at least 2 test methods
- [ ] Task 5 (gateway throws) has at least 1 test method
- [ ] Task 6 (`calculateTotal`) has at least 2 test methods
- [ ] Task 7 (email content) has at least 2 test methods
- [ ] `expectException()` is called BEFORE throwing calls
- [ ] All tests pass with `./vendor/bin/phpunit`

---

## Running Your Tests

```bash
# Run only this challenge
./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.2-unit-testing-with-fakes-and-stubs/challenge/starter/OrderServiceTest.php

# Verbose output
./vendor/bin/phpunit --testdox module-5-testing-and-tdd/lesson-5.2-unit-testing-with-fakes-and-stubs/challenge/starter/OrderServiceTest.php
```

---

## Expected Output (abridged, testdox)

```
OrderService
 ✔ Place order returns success true when all dependencies succeed
 ✔ Place order returns non-null integer order id on success
 ✔ Place order returns correct total cents
 ✔ Place order sends exactly one email on success
 ✔ Place order sends email to the customer email address
 ✔ Place order email subject contains product name
 ✔ Place order returns failure when payment is declined
 ✔ Place order error is payment declined when gateway returns false
 ✔ No email sent when payment is declined
 ✔ Place order throws domain exception when product not found
 ✔ Domain exception message contains the product id
 ✔ No email sent when product is not found
 ✔ Place order propagates runtime exception from gateway
 ✔ No email sent before gateway exception escapes
 ✔ Calculate total returns price times qty for valid product
 ✔ Calculate total throws domain exception for unknown product id
 ✔ Email body contains product name
 ✔ Email body contains formatted total amount

OK (18 tests, N assertions)
```