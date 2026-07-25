# Code Challenge — Lesson 1.1: Interfaces
> **Refactor a tightly coupled class to depend on an interface**

---

## The Brief

You have inherited a small e-commerce codebase. The lead developer left in a hurry and the `InvoiceService` class is a mess. It is tightly coupled to a specific payment gateway and a specific receipt printer. Your job is to fix this using interfaces — without changing what the application *does*, only how it is structured.

---

## What is Wrong With the Starter Code

Open `starter.php` and read it carefully. You will find these problems:

1. `InvoiceService` creates its own `StripeGateway` and `PdfReceiptPrinter` with `new` inside its constructor — it is glued to those specific classes.
2. There is no way to swap in a different payment gateway (e.g. PayFast, PayPal) without editing `InvoiceService`.
3. There is no way to test `InvoiceService` without making real payment calls and generating real PDF files.
4. The `processPayment()` and `printReceipt()` methods have no shared contract — they are just whatever those two classes happen to provide.

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Define the interfaces
Create two interfaces:
- `PaymentGateway` with a method `charge(float $amount, string $currency, string $token): bool`
- `ReceiptPrinter` with a method `print(array $invoiceData): void`

### Task 2 — Implement the interfaces
Update `StripeGateway` to `implement PaymentGateway`.
Update `PdfReceiptPrinter` to `implement ReceiptPrinter`.

### Task 3 — Add new implementations (to prove the design works)
Create `PayFastGateway implements PaymentGateway` — simulate a different payment provider.
Create `EmailReceiptPrinter implements ReceiptPrinter` — send the receipt by email instead.

### Task 4 — Refactor InvoiceService
- Remove the `new StripeGateway()` and `new PdfReceiptPrinter()` from the constructor.
- Accept `PaymentGateway` and `ReceiptPrinter` as **constructor parameters** (type-hinted against the interfaces).
- `InvoiceService` should have **zero** `new` keywords inside it after your refactor.

### Task 5 — Wire it up
At the bottom of the file, create four different `InvoiceService` combinations and call `process()` on each:
1. Stripe + PDF
2. Stripe + Email
3. PayFast + PDF
4. PayFast + Email

---

## Acceptance Criteria

You know you are done when:

- [ ] Both interfaces are defined with the correct method signatures.
- [ ] All four classes (`StripeGateway`, `PayFastGateway`, `PdfReceiptPrinter`, `EmailReceiptPrinter`) implement the appropriate interface.
- [ ] `InvoiceService` constructor takes `PaymentGateway` and `ReceiptPrinter` parameters — no `new` inside the class body.
- [ ] All four wiring combinations run and produce correct output.
- [ ] You can add a fifth payment gateway without touching `InvoiceService` at all.

---

## Hints

- If you are unsure about syntax, look back at `examples/01-defining-and-implementing.php`.
- For the type hints in `InvoiceService`, look at `examples/03-type-hints-and-polymorphism.php`.
- Do not change the `process()` method's logic — only change how its dependencies arrive.
- Money is formatted with `number_format($amount, 2)`. Interpolating a float straight into a string (`"R{$amount}"`) prints `R1500`, not `R1500.00` — PHP drops trailing zeros. Keep the `number_format()` calls if you want your output to match the expected output below.

---

## Expected Output

```
=== Stripe + PDF ===
[STRIPE] Charging R1500.00 ZAR on token tok_abc123... OK
[PDF RECEIPT] Printing invoice #INV-001 for Alice Smith (R1500.00)

=== Stripe + Email ===
[STRIPE] Charging R1500.00 ZAR on token tok_abc123... OK
[EMAIL RECEIPT] Sending invoice #INV-001 to alice@example.com (R1500.00)

=== PayFast + PDF ===
[PAYFAST] Initiating payment of R1500.00 ZAR (token: tok_abc123)... OK
[PDF RECEIPT] Printing invoice #INV-001 for Alice Smith (R1500.00)

=== PayFast + Email ===
[PAYFAST] Initiating payment of R1500.00 ZAR (token: tok_abc123)... OK
[EMAIL RECEIPT] Sending invoice #INV-001 to alice@example.com (R1500.00)
```