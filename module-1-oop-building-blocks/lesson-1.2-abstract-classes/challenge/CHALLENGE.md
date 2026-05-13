# Code Challenge — Lesson 1.2: Abstract Classes

> **Extract shared logic from two similar classes into an abstract base**

---

## The Brief

You have inherited a billing system with two payment processor classes: `StripeProcessor` and `PayFastProcessor`. A junior developer wrote them independently, and they share a large amount of duplicated logic — validation, fee calculation, logging, and a receipt-generation pipeline. Every change to the shared logic currently requires editing both files.

Your job is to:
1. Extract the duplicated code into an abstract base class.
2. Keep only what is unique to each processor in the concrete subclasses.
3. Apply the Template Method Pattern to the receipt pipeline.

---

## What is Wrong With the Starter Code

Open `starter.php` and read both classes carefully. You will find:

**Duplication 1 — Constructor validation**
Both constructors validate that `$apiKey` is non-empty and `$merchantId` matches the format `MERCH-[0-9]{6}`. This is identical in both classes.

**Duplication 2 — Fee calculation logic**
Both classes have a `calculateFee(float $amount): float` method. The formula is identical: `round($amount * 0.029 + 0.30, 2)` (Stripe-style flat rate). Only the fee *label* differs.

**Duplication 3 — Logging**
Both classes have a `logTransaction(string $type, float $amount, bool $success): void` that builds an identical log string. Only the channel prefix differs.

**Duplication 4 — Receipt pipeline**
Both `generateReceipt()` methods follow the same three-step sequence:
1. Build a header (unique per processor)
2. Build a body (shared format)
3. Build a footer (shared format)

The body and footer are word-for-word identical. Only the header differs.

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Create the abstract base class `PaymentProcessor`
- Constructor takes `string $apiKey` and `string $merchantId` and validates both (extract the shared validation here).
- Abstract method: `charge(float $amount, string $currency, string $token): bool`
- Abstract method: `getProcessorName(): string`
- Abstract protected method: `buildReceiptHeader(float $amount, string $currency): string`
- Concrete method: `calculateFee(float $amount): float` — the shared formula
- Concrete protected method: `logTransaction(string $type, float $amount, bool $success): void`
- Concrete **final** public method: `generateReceipt(float $amount, string $currency): string` — the template method that calls `buildReceiptHeader()`, then the shared body, then the shared footer

### Task 2 — Refactor `StripeProcessor extends PaymentProcessor`
Remove everything that now lives in the base class. Keep only:
- `charge()` — Stripe-specific implementation
- `getProcessorName()` — returns `'Stripe'`
- `buildReceiptHeader()` — Stripe-specific header format

### Task 3 — Refactor `PayFastProcessor extends PaymentProcessor`
Same as Task 2 but for PayFast. Keep only the three unique methods.

### Task 4 — Wire it up
At the bottom of the file, create both processors and call `charge()` and `generateReceipt()` on each. Confirm the output matches the expected output below.

---

## Acceptance Criteria

- [ ] `PaymentProcessor` is abstract and cannot be instantiated directly.
- [ ] Constructor validation lives only in `PaymentProcessor` — not repeated in subclasses.
- [ ] `calculateFee()` lives only in `PaymentProcessor` — not repeated in subclasses.
- [ ] `logTransaction()` lives only in `PaymentProcessor` — not repeated in subclasses.
- [ ] `generateReceipt()` is `final` in `PaymentProcessor` — subclasses cannot override the pipeline.
- [ ] Each subclass is under 30 lines (only the three unique methods remain).
- [ ] Adding a third processor (`PayPalProcessor`) only requires extending `PaymentProcessor` and implementing the three unique methods — no changes to existing code.

---

## Expected Output

```
=== Stripe ===
[STRIPE] Charging R500.00 ZAR on token tok_abc123
[STRIPE] LOG charge R500.00 SUCCESS
Fee: R14.80

--- RECEIPT ---
Stripe Payment Receipt
Transaction: tok_abc123 | R500.00 ZAR
Fee: R14.80 | Net: R485.20
---
Merchant: MERCH-001234
Processed at: [timestamp]
--- END ---

=== PayFast ===
[PAYFAST] Initiating R500.00 ZAR via token tok_pf456
[PAYFAST] LOG charge R500.00 SUCCESS
Fee: R14.80

--- RECEIPT ---
PayFast Payment Confirmation
Transaction: tok_pf456 | R500.00 ZAR
Fee: R14.80 | Net: R485.20
---
Merchant: MERCH-001234
Processed at: [timestamp]
--- END ---

=== Constructor validation ===
Caught: API key cannot be empty.
Caught: Invalid merchant ID format. Expected: MERCH-XXXXXX
```

---

## Hints

- Start with the abstract base class before touching the concrete classes.
- The `generateReceipt()` template method should call: `buildReceiptHeader()` → shared body lines → shared footer. Mark it `final`.
- `logTransaction()` uses `get_class($this)` or `$this->getProcessorName()` to get the prefix — it works from the abstract class because `$this` always refers to the concrete subclass at runtime.
- See `examples/05-template-method-pattern.php` for the full Template Method pattern reference.