# Code Challenge — Lesson 5.5: Testing Behaviours, Not Layouts

> **Identify 5 brittle tests, name each anti-pattern, and rewrite them to test behaviour.**

---

## The Brief

`starter/BrittleTestSuite.php` contains `SubscriptionService` and a test class with **five brittle tests**. Every test currently passes. Your job:

1. Read each test carefully
2. Identify which anti-pattern it represents (see below)
3. Rewrite it as a behaviour test in `starter/BrittleTestSuite.php`
4. Apply the refactor described at the bottom of the starter file
5. Confirm your rewritten tests still pass after the refactor

---

## The Five Anti-Patterns

| # | Anti-pattern | Symptom |
|---|-------------|---------|
| AP-1 | Constructor parameter count | `ReflectionClass` + `assertCount(N, $params)` |
| AP-2 | Private property existence | `ReflectionProperty` + `assertSame($value, $prop->getValue())` |
| AP-3 | Exact log message strings | `withConsecutive(['info', '...exact wording...'])` |
| AP-4 | Internal method call count | asserting how many times an internal helper was invoked |
| AP-5 | Over-specified return value format | asserting exact internal format (e.g. UUID regex) rather than contract |

---

## The Refactor (applied after you rewrite the tests)

Once your tests are rewritten and passing, apply ALL of these changes to `SubscriptionService` inside the file. **These changes do NOT alter observable behaviour:**

1. Add a 4th constructor parameter: `private string $defaultPlan = 'free'`
2. Rename the private property `$subscriptions` → `$activeSubscriptions`
3. Change log messages:
   - `'Subscription created for ...'` → `'New subscriber: ...'`
   - `'Subscription cancelled for ...'` → `'Subscriber removed: ...'`
4. Change the subscription ID format from `SUB-{timestamp}` to `SUB-{random hex}`

After the refactor:
- Your **rewritten** tests must still pass
- The **original** brittle tests will fail (that is expected — they are testing layout)

---

## Running Your Tests

```bash
# Run your rewritten tests
./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.5-testing-behaviours-not-layouts/challenge/starter/BrittleTestSuite.php

# With testdox
./vendor/bin/phpunit --testdox module-5-testing-and-tdd/lesson-5.5-testing-behaviours-not-layouts/challenge/starter/BrittleTestSuite.php
```

---

## Acceptance Criteria

- [ ] Each of the 5 brittle tests has a corresponding rewritten behaviour test
- [ ] Each rewritten test has a comment naming the anti-pattern it replaces
- [ ] All rewritten tests pass before the refactor
- [ ] All rewritten tests still pass after the refactor is applied
- [ ] No rewritten test uses `ReflectionClass`, `ReflectionProperty`, `withConsecutive`, or `createMock` for log assertions